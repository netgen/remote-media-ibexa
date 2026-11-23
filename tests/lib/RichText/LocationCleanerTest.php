<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Tests\RichText;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Events\Content\BeforeUpdateContentEvent;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentUpdateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo as APIVersionInfo;
use Ibexa\Core\FieldType\Url\UrlStorage\Gateway\DoctrineStorage as UrlGateway;
use Ibexa\FieldTypeRichText\FieldType\RichText\RichTextStorage;
use Ibexa\FieldTypeRichText\FieldType\RichText\RichTextStorage\Gateway\DoctrineStorage as RichTextGateway;
use Netgen\Bundle\RemoteMediaIbexaBundle\EventListener\RichTextLocationCleanerListener;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMediaIbexa\RichText\EditorConfiguration;
use Netgen\RemoteMediaIbexa\RichText\FieldStorage;
use Netgen\RemoteMediaIbexa\RichText\LocationCleaner;
use Netgen\RemoteMediaIbexa\RichText\LocationGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_map;
use function implode;
use function sort;

#[CoversClass(FieldStorage::class)]
#[CoversClass(LocationCleaner::class)]
#[CoversClass(LocationGateway::class)]
#[CoversClass(RichTextLocationCleanerListener::class)]
final class LocationCleanerTest extends TestCase
{
    private Connection $connection;

    private FieldStorage $fieldStorage;

    private LocationCleaner $locationCleaner;

    /**
     * @var int[]
     */
    private array $removedLocationIds = [];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE ezcontentobject_attribute (id INTEGER NOT NULL, version INTEGER NOT NULL, '
            . 'data_type_string VARCHAR(50) NOT NULL, data_text TEXT, PRIMARY KEY (id, version))',
        );
        $this->connection->executeStatement(
            'CREATE TABLE ngrm_remote_media_content_link (id INTEGER PRIMARY KEY, field_id INTEGER NOT NULL, '
            . 'version INTEGER NOT NULL, remote_resource_location_id INTEGER NOT NULL)',
        );
        $this->connection->executeStatement('CREATE TABLE ezurl (id INTEGER PRIMARY KEY, url TEXT)');
        $this->connection->executeStatement(
            'CREATE TABLE ezurl_object_link (contentobject_attribute_id INTEGER NOT NULL, '
            . 'contentobject_attribute_version INTEGER NOT NULL, url_id INTEGER NOT NULL)',
        );

        $provider = $this->createMock(ProviderInterface::class);
        $provider
            ->method('loadLocation')
            ->willReturnCallback(
                static fn (int $id): RemoteResourceLocation => new RemoteResourceLocation(
                    new RemoteResource('folder/image', 'image', 'https://example.com/image.jpg', ''),
                    id: $id,
                ),
            );
        $provider
            ->method('removeLocation')
            ->willReturnCallback(
                function (RemoteResourceLocation $location): void {
                    $this->removedLocationIds[] = (int) $location->getId();
                },
            );

        $this->locationCleaner = new LocationCleaner(new LocationGateway($this->connection), $provider);

        // The real RichText storage, it redeclares the gateway property the decorator can't read
        $gateway = new RichTextGateway(new UrlGateway($this->connection), $this->connection);
        $this->fieldStorage = new FieldStorage(new RichTextStorage($gateway), $gateway, $this->locationCleaner);
    }

    public function testDeletesLocationsOfDeletedVersionOnlyWhenNotReferenced(): void
    {
        // Published version 1, draft version 2 edited copies of 2 and added 3, content 2 is a copy of version 1
        $this->insertRichTextField(10, 1, $this->richText(1, 2));
        $this->insertRichTextField(10, 2, $this->richText(1, 4, 3));
        $this->insertRichTextField(20, 1, $this->richText(1, 2));
        $this->connection->insert('ezurl', ['id' => 1, 'url' => 'https://example.com']);
        $this->connection->insert('ezurl_object_link', ['contentobject_attribute_id' => 10, 'contentobject_attribute_version' => 1, 'url_id' => 1]);
        $this->connection->insert('ezurl_object_link', ['contentobject_attribute_id' => 10, 'contentobject_attribute_version' => 2, 'url_id' => 1]);

        $this->deleteVersion([10], 2);
        $this->locationCleaner->cleanUp();

        self::assertSame([3, 4], $this->getRemovedLocationIds());

        // The decorated storage deleted its own data of the deleted version only
        self::assertSame(
            [1],
            array_map('intval', $this->connection->fetchFirstColumn('SELECT contentobject_attribute_version FROM ezurl_object_link')),
        );
    }

    public function testDeletesLocationsSharedByAllDeletedVersions(): void
    {
        // A translation is deleted from every version, the fields are deleted only after all versions are collected
        $this->insertRichTextField(10, 1, $this->richText(5));
        $this->insertRichTextField(10, 2, $this->richText(5, 6));
        $this->insertRichTextField(11, 2, $this->richText(7));

        $this->fieldStorage->deleteFieldData(new VersionInfo(['versionNo' => 1]), [10], []);
        $this->fieldStorage->deleteFieldData(new VersionInfo(['versionNo' => 2]), [10], []);
        $this->connection->executeStatement('DELETE FROM ezcontentobject_attribute WHERE id = 10');

        $this->locationCleaner->cleanUp();

        self::assertSame([5, 6], $this->getRemovedLocationIds());
    }

    public function testKeepsLocationsReferencedByFieldTypeOrUnparsableRichText(): void
    {
        $this->insertRichTextField(10, 1, $this->richText(8, 9, 13));
        $this->insertRichTextField(30, 1, '<section><eztemplate name="ngremotemedia"><ezvalue key="locationId">9</ezvalue>');
        $this->insertRichTextField(40, 1, $this->richText(113));
        $this->connection->insert(
            'ngrm_remote_media_content_link',
            ['field_id' => 50, 'version' => 1, 'remote_resource_location_id' => 8],
        );

        $this->deleteVersion([10], 1);
        $this->locationCleaner->cleanUp();

        self::assertSame([13], $this->getRemovedLocationIds());
    }

    public function testIgnoresOtherCustomTags(): void
    {
        $this->insertRichTextField(10, 1, $this->richText(14));
        $this->insertRichTextField(
            20,
            1,
            '<section xmlns="http://docbook.org/ns/docbook"><eztemplate name="ngremotemedia"/>'
            . '<eztemplate name="other"><ezconfig><ezvalue key="locationId">14</ezvalue></ezconfig></eztemplate></section>',
        );

        $this->deleteVersion([10], 1);
        $this->locationCleaner->cleanUp();

        self::assertSame([14], $this->getRemovedLocationIds());
    }

    public function testCleanUpRunsOnce(): void
    {
        $this->insertRichTextField(10, 1, $this->richText(15));

        $this->deleteVersion([10], 1);
        $this->locationCleaner->cleanUp();
        $this->locationCleaner->cleanUp();

        self::assertSame([15], $this->getRemovedLocationIds());
    }

    public function testDeletesUnusedStoredLocationsSentWithSavedContent(): void
    {
        // Published version 1 references 1, the draft referenced 2 before an autosave replaced it by the copy 3
        $this->insertRichTextField(10, 1, $this->richText(1));
        $this->insertRichTextField(10, 2, $this->richText(3));

        $this->updateContent('2,1,invalid,0,-5');

        self::assertSame([2], $this->getRemovedLocationIds());
    }

    public function testIgnoresContentSavedWithoutUnusedLocations(): void
    {
        $this->updateContent(null);
        $this->updateContent('');

        self::assertSame([], $this->getRemovedLocationIds());
    }

    /**
     * Saves the content the way the content edit form does, then ends the request.
     */
    private function updateContent(?string $unusedLocations): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(
            new Request([], $unusedLocations !== null ? [EditorConfiguration::UNUSED_LOCATIONS_PARAMETER => $unusedLocations] : []),
        );

        $listener = new RichTextLocationCleanerListener($this->locationCleaner, $requestStack);
        $listener->onBeforeUpdateContent(
            new BeforeUpdateContentEvent($this->createMock(APIVersionInfo::class), $this->createMock(ContentUpdateStruct::class)),
        );
        $listener->onTerminate();
    }

    /**
     * Deletes the fields the way Ibexa legacy storage does: external storage first, then the field rows.
     *
     * @param int[] $fieldIds
     */
    private function deleteVersion(array $fieldIds, int $versionNo): void
    {
        $this->fieldStorage->deleteFieldData(new VersionInfo(['versionNo' => $versionNo]), $fieldIds, []);

        $this->connection->executeStatement(
            'DELETE FROM ezcontentobject_attribute WHERE id IN (?) AND version = ?',
            [$fieldIds, $versionNo],
            [Connection::PARAM_INT_ARRAY],
        );
    }

    private function insertRichTextField(int $id, int $versionNo, string $xml): void
    {
        $this->connection->insert(
            'ezcontentobject_attribute',
            ['id' => $id, 'version' => $versionNo, 'data_type_string' => 'ezrichtext', 'data_text' => $xml],
        );
    }

    private function richText(int ...$locationIds): string
    {
        $elements = array_map(
            static fn (int $locationId): string => '<eztemplate name="ngremotemedia"><ezconfig>'
                . '<ezvalue key="locationId">' . $locationId . '</ezvalue><ezvalue key="variationName">small</ezvalue>'
                . '</ezconfig></eztemplate>',
            $locationIds,
        );

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'xmlns:ezxhtml="http://ibexa.co/xmlns/dxp/docbook/xhtml" xmlns:ezcustom="http://ibexa.co/xmlns/dxp/docbook/custom" '
            . 'version="5.0-variant ezpublish-1.0"><para>Text</para>' . implode('', $elements) . '</section>' . "\n";
    }

    /**
     * @return int[]
     */
    private function getRemovedLocationIds(): array
    {
        $removedLocationIds = $this->removedLocationIds;
        sort($removedLocationIds);

        return $removedLocationIds;
    }
}
