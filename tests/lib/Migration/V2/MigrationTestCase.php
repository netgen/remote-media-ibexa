<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Tests\Migration\V2;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMediaIbexa\API\Values\NgRemoteMediaContentLink;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function dirname;
use function sys_get_temp_dir;

/**
 * Runs the migration on an in-memory SQLite database with the Netgen Remote Media 3 tables and the
 * Ibexa legacy storage columns the migration uses.
 */
abstract class MigrationTestCase extends TestCase
{
    protected EntityManagerInterface $entityManager;

    protected Connection $connection;

    protected function setUp(): void
    {
        $coreMappings = dirname((string) (new ReflectionClass(RemoteResource::class))->getFileName(), 4) . '/bundle/Resources/config/doctrine';

        $config = ORMSetup::createConfiguration(true, sys_get_temp_dir());
        $config->setMetadataDriverImpl(
            new SimplifiedXmlDriver(
                [
                    $coreMappings => 'Netgen\RemoteMedia\API\Values',
                    dirname(__DIR__, 4) . '/bundle/Resources/config/doctrine' => 'Netgen\RemoteMediaIbexa\API\Values',
                ],
            ),
        );

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->entityManager = new EntityManager($this->connection, $config);

        (new SchemaTool($this->entityManager))->createSchema(
            [
                $this->entityManager->getClassMetadata(RemoteResource::class),
                $this->entityManager->getClassMetadata(RemoteResourceLocation::class),
                $this->entityManager->getClassMetadata(NgRemoteMediaContentLink::class),
            ],
        );

        $this->connection->executeStatement(
            'CREATE TABLE ezcontentobject_attribute (id INTEGER NOT NULL, version INTEGER NOT NULL, contentobject_id INTEGER NOT NULL, '
            . 'contentclassattribute_id INTEGER NOT NULL, data_type_string VARCHAR(50) NOT NULL, data_text TEXT, PRIMARY KEY (id, version))',
        );
        $this->connection->executeStatement(
            'CREATE TABLE ezcontentobject_version (contentobject_id INTEGER NOT NULL, version INTEGER NOT NULL, status INTEGER NOT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE ezcontentclass_attribute (id INTEGER NOT NULL, version INTEGER NOT NULL, contentclass_id INTEGER NOT NULL, '
            . 'identifier VARCHAR(50) NOT NULL, data_type_string VARCHAR(50) NOT NULL, data_text5 TEXT)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE ezcontentclass (id INTEGER NOT NULL, version INTEGER NOT NULL, identifier VARCHAR(50) NOT NULL)',
        );
    }

    protected function insertField(int $id, int $version, int $contentId, int $fieldDefinitionId, string $fieldType, string $data): void
    {
        $this->connection->insert(
            'ezcontentobject_attribute',
            [
                'id' => $id,
                'version' => $version,
                'contentobject_id' => $contentId,
                'contentclassattribute_id' => $fieldDefinitionId,
                'data_type_string' => $fieldType,
                'data_text' => $data,
            ],
        );

        $versionExists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ezcontentobject_version WHERE contentobject_id = ? AND version = ?',
            [$contentId, $version],
        ) > 0;

        if (!$versionExists) {
            // Status 1 is published
            $this->connection->insert('ezcontentobject_version', ['contentobject_id' => $contentId, 'version' => $version, 'status' => 1]);
        }
    }

    protected function insertResource(string $remoteId, ?string $altText, ?string $caption): void
    {
        $this->entityManager->persist(
            new RemoteResource(
                remoteId: $remoteId,
                type: RemoteResource::TYPE_IMAGE,
                url: 'https://res.cloudinary.com/demo/image/upload/' . $remoteId,
                md5: '',
                name: $remoteId,
                originalFilename: $remoteId,
                altText: $altText,
                caption: $caption,
            ),
        );

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadResource(string $remoteId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT alt_text, caption FROM ngrm_remote_resource WHERE remote_id = ?',
            [$remoteId],
        );

        self::assertIsArray($row);

        return $row;
    }
}
