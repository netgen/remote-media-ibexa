<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\FieldTypeRichText\RichText\ValidatorInterface;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Throwable;

use function array_key_last;
use function array_merge;
use function array_search;
use function count;
use function implode;
use function preg_replace;
use function serialize;
use function sprintf;

use const LIBXML_NONET;

/**
 * Migrates "ngremotemedia" custom tags of Netgen Remote Media 2 in RichText fields: a remote resource location
 * is created for every tag, and the tag is converted to the Netgen Remote Media 3 format, see RichTextConverter.
 *
 * Resources which aren't stored yet (not used in any field) are loaded from the remote. Archived versions are
 * not migrated.
 */
final class RichTextMigration
{
    private const FIELD_TYPE = 'ezrichtext';

    // Ibexa 4 legacy storage tables (the constants of the storage gateways are internal)
    private const FIELD_TABLE = 'ezcontentobject_attribute';
    private const VERSION_TABLE = 'ezcontentobject_version';

    /**
     * IDs of locations created during the run, by field ID, remote ID and crop settings, so versions of a field
     * share the location of the same tag, as with RichText edited with Netgen Remote Media 3.
     *
     * @var array<string, int>
     */
    private array $locationIds = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly ValueMapper $valueMapper,
        private readonly RichTextConverter $converter,
        private readonly ValidatorInterface $docBookValidator,
        private readonly ValidatorInterface $customTemplateValidator,
        private readonly ConfigResolverInterface $configResolver,
    ) {}

    public function countFields(): int
    {
        return (int) $this->fetchOne($this->createFieldsQuery()->select('COUNT(*)'));
    }

    /**
     * @param callable(int): void $progress called after each batch, with the number of processed fields
     */
    public function migrate(int $batchSize, callable $progress): Report
    {
        $report = new Report();
        $this->resourceRegistry->load();
        $this->locationIds = [];

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
        $fields = [];
        $remoteIds = [];

        foreach ($rows as $row) {
            $document = new DOMDocument();
            $xml = (string) ($row['data_text'] ?? '');

            if ($xml === '' || !@$document->loadXML($xml, LIBXML_NONET)) {
                $report->warnings[] = sprintf('%s: invalid XML', $this->describe($row));

                continue;
            }

            $tags = $this->converter->findTags($document);

            if (count($tags) === 0) {
                ++$report->skipped;

                continue;
            }

            foreach ($tags as $tag) {
                if ($tag->getResourceId() !== '') {
                    $remoteIds[] = $this->getRemoteId($tag);
                }
            }

            $fields[] = [$row, $document, $tags];
        }

        foreach ($this->resourceRegistry->storeFromRemote($remoteIds) as $reason) {
            $report->warnings[] = 'RichText: ' . $reason;
        }

        $this->entityManager->clear();

        foreach ($fields as [$row, $document, $tags]) {
            $this->migrateField($row, $document, $tags, $report);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param \Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag[] $tags
     */
    private function migrateField(array $row, DOMDocument $document, array $tags, Report $report): void
    {
        $connection = $this->getConnection();
        $errorsBefore = $this->validate($document);
        $createdLocations = [];

        $connection->beginTransaction();

        try {
            $converted = $this->converter->convert(
                $document,
                function (RichTextTag $tag) use ($row, &$createdLocations): ?int {
                    return $this->resolveLocationId($tag, (int) $row['id'], $createdLocations);
                },
            );

            $newErrors = $this->diffErrors($errorsBefore, $this->validate($document));

            if ($converted === 0 || count($newErrors) > 0) {
                $connection->rollBack();

                foreach ($createdLocations as $key) {
                    unset($this->locationIds[$key]);
                }

                $report->warnings[] = $converted === 0 ?
                    sprintf('%s: no tag could be converted', $this->describe($row)) :
                    sprintf('%s: not migrated, the result is invalid: %s', $this->describe($row), implode('; ', $newErrors));

                return;
            }

            $connection->update(
                self::FIELD_TABLE,
                ['data_text' => $document->saveXML()],
                ['id' => (int) $row['id'], 'version' => (int) $row['version']],
            );

            $connection->commit();
        } catch (Throwable $t) {
            $connection->rollBack();

            throw $t;
        } finally {
            $this->entityManager->clear();
        }

        ++$report->migrated;

        $leftTags = $this->converter->findTags($document);

        if (count($leftTags) > 0) {
            $report->warnings[] = sprintf(
                '%s: %d of %d tags left as they are (resource missing on the remote, or the tag is not in a paragraph)',
                $this->describe($row),
                count($leftTags),
                count($tags),
            );
        }

        foreach ($this->getDroppedCaptions($tags, $leftTags) as [$resourceId, $caption]) {
            $report->warnings[] = sprintf(
                '%s: caption of resource "%s" dropped, the tag doesn\'t support it: "%s"',
                $this->describe($row),
                $resourceId,
                $caption,
            );
        }
    }

    /**
     * Resource IDs and captions of converted tags.
     *
     * @param \Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag[] $tags
     * @param \Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag[] $leftTags
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function getDroppedCaptions(array $tags, array $leftTags): array
    {
        $leftCaptions = [];

        foreach ($leftTags as $tag) {
            $leftCaptions[] = $tag->getResourceId() . '|' . $tag->getCaption();
        }

        $droppedCaptions = [];

        foreach ($tags as $tag) {
            $caption = $tag->getCaption();

            if ($caption === null) {
                continue;
            }

            $index = array_search($tag->getResourceId() . '|' . $caption, $leftCaptions, true);

            if ($index !== false) {
                unset($leftCaptions[$index]);

                continue;
            }

            $droppedCaptions[] = [$tag->getResourceId(), $caption];
        }

        return $droppedCaptions;
    }

    /**
     * @param string[] $createdLocations
     */
    private function resolveLocationId(RichTextTag $tag, int $fieldId, array &$createdLocations): ?int
    {
        if ($tag->getResourceId() === '') {
            return null;
        }

        $remoteId = $this->getRemoteId($tag);
        $resource = $this->resourceRegistry->get($remoteId);

        if ($resource === null) {
            return null;
        }

        $cropSettings = $this->valueMapper->toCropSettings($tag->getCoords());
        $key = $fieldId . '|' . $remoteId . '|' . serialize($cropSettings);

        if (!isset($this->locationIds[$key])) {
            $location = new RemoteResourceLocation(
                $resource,
                (string) $this->configResolver->getParameter('richtext.location_source', 'netgen_remote_media_ibexa'),
                $cropSettings,
            );

            $this->entityManager->persist($location);
            $this->entityManager->flush();

            $this->locationIds[$key] = (int) $location->getId();
            $createdLocations[] = $key;
        }

        return $this->locationIds[$key];
    }

    private function getRemoteId(RichTextTag $tag): string
    {
        // Netgen Remote Media 2 RichText supported only uploaded (public) resources
        return $this->valueMapper->toRemoteId('upload', $tag->getResourceType(), $tag->getResourceId());
    }

    /**
     * @return string[]
     */
    private function validate(DOMDocument $document): array
    {
        $errors = array_merge(
            $this->docBookValidator->validateDocument($document),
            $this->customTemplateValidator->validateDocument($document),
        );

        // Positions of errors change with the document, they are not compared
        foreach ($errors as $index => $error) {
            $errors[$index] = (string) preg_replace('/^(\w+) in \d+:\d+: /', '$1: ', $error);
        }

        return $errors;
    }

    /**
     * Errors in $after which are not in $before, as many times as they are more often in $after.
     *
     * @param string[] $before
     * @param string[] $after
     *
     * @return string[]
     */
    private function diffErrors(array $before, array $after): array
    {
        $newErrors = [];

        foreach ($after as $error) {
            $index = array_search($error, $before, true);

            if ($index === false) {
                $newErrors[] = $error;

                continue;
            }

            unset($before[$index]);
        }

        return $newErrors;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadFields(int $lastId, int $lastVersion, int $limit): array
    {
        $query = $this->createFieldsQuery()
            ->select('f.id, f.version, f.contentobject_id, f.data_text')
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
            ->where('f.data_type_string = :field_type')
            ->andWhere('f.data_text LIKE :tag')
            ->andWhere('v.status <> :archived')
            ->setParameter('field_type', self::FIELD_TYPE)
            ->setParameter('tag', '%name="ngremotemedia"%')
            ->setParameter('archived', VersionInfo::STATUS_ARCHIVED, ParameterType::INTEGER);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function describe(array $row): string
    {
        return sprintf('Content %d, field %d, version %d', $row['contentobject_id'], $row['id'], $row['version']);
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
