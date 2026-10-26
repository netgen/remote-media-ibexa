<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Tests\Migration\V2;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\FieldTypeRichText\RichText\ValidatorInterface;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Search\Result;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMediaIbexa\Migration\V2\ResourceRegistry;
use Netgen\RemoteMediaIbexa\Migration\V2\RichTextConverter;
use Netgen\RemoteMediaIbexa\Migration\V2\RichTextMigration;
use Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag;
use Netgen\RemoteMediaIbexa\Migration\V2\ValueMapper;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_map;
use function preg_match_all;
use function sprintf;

#[CoversClass(RichTextMigration::class)]
#[CoversClass(ResourceRegistry::class)]
#[CoversClass(RichTextConverter::class)]
#[CoversClass(RichTextTag::class)]
#[CoversClass(ValueMapper::class)]
final class RichTextMigrationTest extends MigrationTestCase
{
    private RichTextMigration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = self::createStub(ProviderInterface::class);
        $provider->method('search')->willReturn(
            new Result(
                1,
                null,
                [
                    new RemoteResource(
                        remoteId: 'upload|image|remote',
                        type: RemoteResource::TYPE_IMAGE,
                        url: 'https://res.cloudinary.com/demo/image/upload/remote',
                        md5: '',
                        name: 'remote',
                        originalFilename: 'remote',
                    ),
                ],
            ),
        );

        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validateDocument')->willReturn([]);

        $configResolver = self::createStub(ConfigResolverInterface::class);
        $configResolver->method('getParameter')->willReturn('ibexa_richtext');

        $this->migration = new RichTextMigration(
            $this->entityManager,
            new ResourceRegistry($this->entityManager, $provider),
            new ValueMapper(),
            new RichTextConverter(),
            $validator,
            $validator,
            $configResolver,
        );
    }

    public function testMigrateWritesCaptionOverridesPerTag(): void
    {
        $this->insertResource('upload|image|stored', 'Cloud alt', 'Cloud caption');

        $this->insertRichTextField(
            1,
            $this->createV2Tag('stored', 'Cloud caption')
            . $this->createV2Tag('stored', 'Tag caption')
            . $this->createV2Tag('stored', '')
            . $this->createV2Tag('remote', 'Remote tag caption'),
        );
        $this->insertRichTextField(2, $this->createV2Tag('stored', 'Tag caption'));

        $report = $this->migration->migrate(10, static function (): void {});

        self::assertSame(2, $report->migrated);
        self::assertSame([], $report->warnings);

        $version1 = $this->loadTagLocations(1);
        $version2 = $this->loadTagLocations(2);

        self::assertSame(
            [
                ['upload|image|stored', null, null],
                ['upload|image|stored', null, 'Tag caption'],
                ['upload|image|stored', null, null],
                ['upload|image|remote', null, 'Remote tag caption'],
            ],
            array_map(fn (int $locationId): array => $this->loadLocation($locationId), $version1),
        );

        // A tag without caption shares the location of the tag with the caption of the resource
        self::assertSame($version1[0], $version1[2]);
        // Versions of a field share the location of the same tag
        self::assertSame([$version1[1]], $version2);

        // Captions of tags are not taken over by resources, Netgen Remote Media 2 never stored them on Cloudinary
        self::assertSame(['alt_text' => 'Cloud alt', 'caption' => 'Cloud caption'], $this->loadResource('upload|image|stored'));
        self::assertSame(['alt_text' => null, 'caption' => null], $this->loadResource('upload|image|remote'));
    }

    private function insertRichTextField(int $version, string $content): void
    {
        $this->insertField(
            5,
            $version,
            42,
            20,
            'ezrichtext',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink"'
            . ' xmlns:ezxhtml="http://ibexa.co/xmlns/dxp/docbook/xhtml" xmlns:ezcustom="http://ibexa.co/xmlns/dxp/docbook/custom"'
            . ' version="5.0-variant ezpublish-1.0">' . $content . '</section>',
        );
    }

    private function createV2Tag(string $resourceId, string $caption): string
    {
        return sprintf(
            '<para><eztemplateinline name="ngremotemedia"><ezconfig><ezvalue key="caption">%s</ezvalue>'
            . '<ezvalue key="coords">[]</ezvalue><ezvalue key="resourceId">%s</ezvalue>'
            . '<ezvalue key="resourceType">image</ezvalue></ezconfig></eztemplateinline></para>',
            $caption,
            $resourceId,
        );
    }

    /**
     * @return int[]
     */
    private function loadTagLocations(int $version): array
    {
        $xml = (string) $this->connection->fetchOne(
            'SELECT data_text FROM ezcontentobject_attribute WHERE id = 5 AND version = ?',
            [$version],
        );

        preg_match_all('#<ezvalue key="locationId">(\d+)</ezvalue>#', $xml, $matches);

        return array_map('intval', $matches[1]);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function loadLocation(int $locationId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT r.remote_id, l.alt_text_override, l.caption_override FROM ngrm_remote_resource_location l '
            . 'INNER JOIN ngrm_remote_resource r ON r.id = l.remote_resource_id WHERE l.id = ?',
            [$locationId],
        );

        self::assertIsArray($row);

        return [(string) $row['remote_id'], $row['alt_text_override'], $row['caption_override']];
    }
}
