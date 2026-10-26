<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Tests\Migration\V2;

use Netgen\RemoteMedia\API\Factory\RemoteResource as RemoteResourceFactory;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMediaIbexa\Converter\Hash;
use Netgen\RemoteMediaIbexa\FieldType\Type;
use Netgen\RemoteMediaIbexa\Migration\V2\FieldMigration;
use Netgen\RemoteMediaIbexa\Migration\V2\ResourceRegistry;
use Netgen\RemoteMediaIbexa\Migration\V2\ValueMapper;
use PHPUnit\Framework\Attributes\CoversClass;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(FieldMigration::class)]
#[CoversClass(ResourceRegistry::class)]
#[CoversClass(ValueMapper::class)]
final class FieldMigrationTest extends MigrationTestCase
{
    private FieldMigration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->insert('ezcontentclass', ['id' => 1, 'version' => 0, 'identifier' => 'article']);
        $this->connection->insert(
            'ezcontentclass_attribute',
            ['id' => 10, 'version' => 0, 'contentclass_id' => 1, 'identifier' => 'image', 'data_type_string' => 'ngremotemedia'],
        );

        $provider = self::createStub(ProviderInterface::class);

        $this->migration = new FieldMigration(
            $this->entityManager,
            new ResourceRegistry($this->entityManager, $provider),
            new class implements RemoteResourceFactory {
                public function create(mixed $data): RemoteResource
                {
                    return new RemoteResource(
                        remoteId: 'upload|image|' . $data['public_id'],
                        type: RemoteResource::TYPE_IMAGE,
                        url: $data['secure_url'],
                        md5: '',
                        name: $data['public_id'],
                        originalFilename: $data['public_id'],
                        altText: $data['context']['custom']['alt_text'] ?? null,
                        caption: $data['context']['custom']['caption'] ?? null,
                    );
                }
            },
            new ValueMapper(),
            new Type(new Hash($provider), $provider, 'ngremotemedia'),
        );
    }

    public function testMigrateFieldsWritesOverridesPerUsage(): void
    {
        // Field 1 is a translation with two versions, field 2 another translation of the same content
        $this->insertV2Field(1, 1, 'folder/image', 'Cloud alt', 'Cloud caption');
        $this->insertV2Field(1, 2, 'folder/image', 'Version 2 alt', 'Cloud caption');
        $this->insertV2Field(2, 1, 'folder/image', '', 'Croatian caption');
        $this->insertV2Field(2, 2, 'folder/image', 'Cloud alt', '   ');

        // Batches of 2: the second batch uses the resource stored by the first one
        $report = $this->migration->migrateFields(2, static function (): void {});

        self::assertSame(4, $report->migrated);
        self::assertSame([], $report->warnings);

        self::assertSame(['alt_text' => 'Cloud alt', 'caption' => 'Cloud caption'], $this->loadResource('upload|image|folder/image'));

        self::assertSame(
            [
                [1, 1, null, null],
                [1, 2, 'Version 2 alt', null],
                [2, 1, null, 'Croatian caption'],
                [2, 2, null, null],
            ],
            $this->loadLocations(),
        );
    }

    public function testMigrateFieldsDoesNotChangeStoredResource(): void
    {
        $this->insertResource('upload|image|stored', 'Stored alt', null);

        $this->insertV2Field(1, 1, 'stored', 'Stored alt', 'Usage caption');
        $this->insertV2Field(2, 1, 'stored', 'Usage alt', '');

        $report = $this->migration->migrateFields(10, static function (): void {});

        self::assertSame(2, $report->migrated);

        // Values of a usage are not taken over by the resource: refreshing it from Cloudinary would remove them
        self::assertSame(['alt_text' => 'Stored alt', 'caption' => null], $this->loadResource('upload|image|stored'));

        self::assertSame(
            [
                [1, 1, null, 'Usage caption'],
                [2, 1, 'Usage alt', null],
            ],
            $this->loadLocations(),
        );
    }

    private function insertV2Field(int $id, int $version, string $resourceId, string $altText, string $caption): void
    {
        $this->insertField(
            $id,
            $version,
            42,
            10,
            'ngremotemedia',
            json_encode(
                [
                    'resourceId' => $resourceId,
                    'resourceType' => 'image',
                    'type' => 'upload',
                    'secure_url' => 'https://res.cloudinary.com/demo/image/upload/' . $resourceId,
                    'variations' => [],
                    'metaData' => ['alt_text' => $altText, 'caption' => $caption],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    /**
     * @return array<int, array{0: int, 1: int, 2: ?string, 3: ?string}>
     */
    private function loadLocations(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT cl.field_id, cl.version, l.alt_text_override, l.caption_override FROM ngrm_remote_media_content_link cl '
            . 'INNER JOIN ngrm_remote_resource_location l ON l.id = cl.remote_resource_location_id ORDER BY cl.field_id, cl.version',
        );

        $locations = [];

        foreach ($rows as $row) {
            $locations[] = [(int) $row['field_id'], (int) $row['version'], $row['alt_text_override'], $row['caption_override']];
        }

        return $locations;
    }
}
