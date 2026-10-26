<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Netgen\RemoteMedia\API\Factory\RemoteResource as RemoteResourceFactory;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMedia\Exception\Factory\InvalidDataException;
use Netgen\RemoteMediaIbexa\API\Values\NgRemoteMediaContentLink;
use Netgen\RemoteMediaIbexa\FieldType\Type;
use Throwable;

use function array_column;
use function array_diff;
use function array_key_last;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * Migrates values of the Netgen Remote Media 2 field type (JSON in the field's data_text) to remote resources,
 * remote resource locations and content links of Netgen Remote Media 3.
 *
 * Resources which aren't stored yet are loaded from the remote, so the alt text and caption of field values are
 * compared with the ones on the remote. A resource which can't be loaded from the remote is created from the first
 * field value using it, so the field is migrated anyway.
 *
 * Archived versions are not migrated: their fields are empty with Netgen Remote Media 3.
 */
final class FieldMigration
{
    // Ibexa 4 legacy storage tables (the constants of the storage gateways are internal)
    private const FIELD_TABLE = 'ezcontentobject_attribute';
    private const VERSION_TABLE = 'ezcontentobject_version';
    private const FIELD_DEFINITION_TABLE = 'ezcontentclass_attribute';
    private const CONTENT_TYPE_TABLE = 'ezcontentclass';

    /**
     * Remote IDs requested from the remote during the run, so resources which couldn't be stored from the remote
     * aren't requested again.
     *
     * @var array<string, true>
     */
    private array $requestedRemoteIds = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly RemoteResourceFactory $remoteResourceFactory,
        private readonly ValueMapper $valueMapper,
        private readonly Type $fieldType,
    ) {}

    /**
     * Stores the default field settings to field definitions without settings (Netgen Remote Media 2 had none).
     *
     * @return int number of updated field definitions
     */
    public function migrateFieldDefinitions(): int
    {
        $settings = [];

        foreach ($this->fieldType->getSettingsSchema() as $name => $schema) {
            $settings[$name] = $schema['default'];
        }

        $query = $this->getConnection()->createQueryBuilder()
            ->update(self::FIELD_DEFINITION_TABLE)
            ->set('data_text5', ':settings')
            ->where('data_type_string = :field_type')
            ->andWhere("data_text5 IS NULL OR data_text5 = ''")
            ->setParameter('settings', json_encode($settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))
            ->setParameter('field_type', $this->fieldType->getFieldTypeIdentifier());

        return (int) $this->getConnection()->executeStatement(
            $query->getSQL(),
            $query->getParameters(),
            $query->getParameterTypes(),
        );
    }

    public function countFields(): int
    {
        return (int) $this->fetchOne($this->createFieldsQuery()->select('COUNT(*)'));
    }

    /**
     * @param callable(int): void $progress called after each batch, with the number of processed fields
     */
    public function migrateFields(int $batchSize, callable $progress): Report
    {
        $report = new Report();
        $this->resourceRegistry->load();
        $this->requestedRemoteIds = [];

        $lastId = 0;
        $lastVersion = 0;

        do {
            $rows = $this->loadFields($lastId, $lastVersion, $batchSize);

            if (count($rows) === 0) {
                break;
            }

            $lastRow = $rows[array_key_last($rows)];
            $lastId = (int) $lastRow['id'];
            $lastVersion = (int) $lastRow['version'];

            $this->migrateBatch($rows, $report);
            $progress(count($rows));
        } while (count($rows) === $batchSize);

        return $report;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function migrateBatch(array $rows, Report $report): void
    {
        $migrated = $this->loadMigratedFields(array_column($rows, 'id'));
        $fields = [];

        foreach ($rows as $row) {
            if (isset($migrated[(int) $row['id']][(int) $row['version']])) {
                ++$report->skipped;

                continue;
            }

            $value = json_decode((string) ($row['data_text'] ?? ''), true);
            $remoteId = is_array($value) ? $this->valueMapper->toRemoteIdFromFieldValue($value) : null;

            if (!is_array($value) || $remoteId === null) {
                ++$report->empty;

                continue;
            }

            $fields[] = [$row, $value, $remoteId];
        }

        $this->storeFromRemote(array_column($fields, 2), $report);

        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            foreach ($fields as [$row, $value, $remoteId]) {
                $data = $this->valueMapper->toCloudinaryData($value);
                $resource = $this->resourceRegistry->get($remoteId) ?? $this->createResource($data, $row, $report);

                if (!$resource instanceof RemoteResource) {
                    continue;
                }

                $location = new RemoteResourceLocation(
                    $resource,
                    // Same as the default location source of the field type's edit form
                    'ibexa_content_' . $row['content_type_identifier'] . '_' . $row['field_identifier'],
                    $this->valueMapper->toCropSettings($value['variations'] ?? null),
                    altTextOverride: $this->valueMapper->toOverride(
                        $data['context']['custom']['alt_text'] ?? null,
                        $this->resourceRegistry->getAltText($remoteId),
                    ),
                    captionOverride: $this->valueMapper->toOverride(
                        $data['context']['custom']['caption'] ?? null,
                        $this->resourceRegistry->getCaption($remoteId),
                    ),
                );

                $this->entityManager->persist(new NgRemoteMediaContentLink((int) $row['id'], (int) $row['version'], $location));

                ++$report->migrated;
            }

            $this->entityManager->flush();
            $this->resourceRegistry->commit();
            $connection->commit();
        } catch (Throwable $t) {
            $connection->rollBack();

            throw $t;
        } finally {
            $this->entityManager->clear();
        }
    }

    /**
     * Stores resources which aren't stored yet from the remote. Every remote ID is requested at most once per run:
     * resources which can't be stored from the remote are created from field values.
     *
     * @param string[] $remoteIds
     */
    private function storeFromRemote(array $remoteIds, Report $report): void
    {
        $remoteIds = array_values(array_diff(array_unique($remoteIds), array_keys($this->requestedRemoteIds)));

        foreach ($remoteIds as $remoteId) {
            $this->requestedRemoteIds[$remoteId] = true;
        }

        foreach ($this->resourceRegistry->storeFromRemote($remoteIds) as $reason) {
            $report->warnings[] = sprintf('Fields: %s, creating the resource from a field value instead', $reason);
        }

        $this->entityManager->clear();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $row
     */
    private function createResource(array $data, array $row, Report $report): ?RemoteResource
    {
        try {
            return $this->resourceRegistry->add($this->remoteResourceFactory->create($data));
        } catch (InvalidDataException|ResourceNotStorableException $e) {
            $report->warnings[] = sprintf(
                'Field %d, version %d: not migrated, %s',
                $row['id'],
                $row['version'],
                $e->getMessage(),
            );

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadFields(int $lastId, int $lastVersion, int $limit): array
    {
        $query = $this->createFieldsQuery();

        $query
            ->select('f.id, f.version, f.data_text, ct.identifier AS content_type_identifier, fd.identifier AS field_identifier')
            ->innerJoin(
                'fd',
                self::CONTENT_TYPE_TABLE,
                'ct',
                'ct.id = fd.contentclass_id AND ct.version = fd.version',
            )
            ->andWhere('f.id > :last_id OR (f.id = :last_id AND f.version > :last_version)')
            ->orderBy('f.id')
            ->addOrderBy('f.version')
            ->setMaxResults($limit)
            ->setParameter('last_id', $lastId, ParameterType::INTEGER)
            ->setParameter('last_version', $lastVersion, ParameterType::INTEGER);

        return $this->fetchAll($query);
    }

    private function createFieldsQuery(): QueryBuilder
    {
        return $this->getConnection()->createQueryBuilder()
            ->from(self::FIELD_TABLE, 'f')
            ->innerJoin(
                'f',
                self::VERSION_TABLE,
                'v',
                'v.contentobject_id = f.contentobject_id AND v.version = f.version',
            )
            ->innerJoin(
                'f',
                self::FIELD_DEFINITION_TABLE,
                'fd',
                // version is the status of a field definition, 0 is defined
                'fd.id = f.contentclassattribute_id AND fd.version = 0',
            )
            ->where('f.data_type_string = :field_type')
            ->andWhere('v.status <> :archived')
            ->setParameter('field_type', $this->fieldType->getFieldTypeIdentifier())
            ->setParameter('archived', VersionInfo::STATUS_ARCHIVED, ParameterType::INTEGER);
    }

    /**
     * @param array<int|string> $fieldIds
     *
     * @return array<int, array<int, true>>
     */
    private function loadMigratedFields(array $fieldIds): array
    {
        $query = $this->getConnection()->createQueryBuilder()
            ->select('field_id, version')
            ->from('ngrm_remote_media_content_link')
            ->where('field_id IN (:field_ids)')
            ->setParameter('field_ids', $fieldIds, Connection::PARAM_INT_ARRAY);

        $rows = $this->fetchAll($query);

        $migrated = [];

        foreach ($rows as $row) {
            $migrated[(int) $row['field_id']][(int) $row['version']] = true;
        }

        return $migrated;
    }

    /**
     * QueryBuilder::executeQuery() needs DBAL 3, Ibexa 4 uses DBAL 2.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(QueryBuilder $query): array
    {
        return $this->getConnection()->fetchAllAssociative($query->getSQL(), $query->getParameters(), $query->getParameterTypes());
    }

    private function fetchOne(QueryBuilder $query): mixed
    {
        return $this->getConnection()->fetchOne($query->getSQL(), $query->getParameters(), $query->getParameterTypes());
    }

    private function getConnection(): Connection
    {
        return $this->entityManager->getConnection();
    }
}
