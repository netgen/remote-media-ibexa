<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Tests\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ibexa\AdminUi\Component\TwigComponent;
use Ibexa\Bundle\FieldTypeRichText\IbexaFieldTypeRichTextBundle;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Ibexa\Core\FieldType\Url\UrlStorage\Gateway\DoctrineStorage as UrlGateway;
use Ibexa\FieldTypeRichText\FieldType\RichText\RichTextStorage;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMediaIbexa\RichText\FieldStorage;
use Netgen\RemoteMediaIbexa\RichText\LocationCleaner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function dirname;

/**
 * Builds the RichText storage decorator from the bundle service configuration around the storage and gateway
 * services of the RichText bundle.
 */
#[CoversClass(FieldStorage::class)]
final class RichTextFieldStorageTest extends TestCase
{
    public function testDecoratesRichTextStorage(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE ezcontentobject_attribute (id INTEGER NOT NULL, version INTEGER NOT NULL, '
            . 'data_type_string VARCHAR(50) NOT NULL, data_text TEXT, PRIMARY KEY (id, version))',
        );
        $connection->executeStatement(
            'CREATE TABLE ngrm_remote_media_content_link (id INTEGER PRIMARY KEY, field_id INTEGER NOT NULL, '
            . 'version INTEGER NOT NULL, remote_resource_location_id INTEGER NOT NULL)',
        );
        $connection->executeStatement('CREATE TABLE ezurl (id INTEGER PRIMARY KEY, url TEXT)');
        $connection->executeStatement(
            'CREATE TABLE ezurl_object_link (contentobject_attribute_id INTEGER NOT NULL, '
            . 'contentobject_attribute_version INTEGER NOT NULL, url_id INTEGER NOT NULL)',
        );

        $connection->insert(
            'ezcontentobject_attribute',
            [
                'id' => 10,
                'version' => 2,
                'data_type_string' => 'ezrichtext',
                'data_text' => '<section xmlns="http://docbook.org/ns/docbook"><eztemplate name="ngremotemedia"><ezconfig>'
                    . '<ezvalue key="locationId">7</ezvalue></ezconfig></eztemplate></section>',
            ],
        );
        $connection->insert('ezurl', ['id' => 1, 'url' => 'https://example.com']);
        $connection->insert('ezurl_object_link', ['contentobject_attribute_id' => 10, 'contentobject_attribute_version' => 2, 'url_id' => 1]);

        $removedLocationIds = [];
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
                static function (RemoteResourceLocation $location) use (&$removedLocationIds): void {
                    $removedLocationIds[] = (int) $location->getId();
                },
            );

        $container = $this->buildContainer();
        $container->set('ibexa.api.storage_engine.legacy.connection', $connection);
        $container->set('ibexa.persistence.connection', $connection);
        $container->set('netgen_remote_media.provider', $provider);
        $container->set('logger', new NullLogger());

        $storage = $container->get(RichTextStorage::class);
        self::assertInstanceOf(FieldStorage::class, $storage);

        $storage->deleteFieldData(new VersionInfo(['versionNo' => 2]), [10], []);
        $connection->executeStatement('DELETE FROM ezcontentobject_attribute WHERE id = 10 AND version = 2');
        $locationCleaner = $container->get(LocationCleaner::class);
        self::assertInstanceOf(LocationCleaner::class, $locationCleaner);
        $locationCleaner->cleanUp();

        self::assertSame([7], $removedLocationIds);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM ezurl_object_link'));
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $richTextConfig = dirname((string) (new ReflectionClass(IbexaFieldTypeRichTextBundle::class))->getFileName()) . '/Resources/config/settings';
        $richTextLoader = new YamlFileLoader($container, new FileLocator($richTextConfig));
        $richTextLoader->load('fieldtype_external_storages.yaml');
        $richTextLoader->load('storage_engines/legacy/external_storage_gateways.yaml');

        $container->register(UrlGateway::class, UrlGateway::class)
            ->addArgument(new Reference('ibexa.api.storage_engine.legacy.connection'));
        $container->register(TwigComponent::class, TwigComponent::class)->setAbstract(true);

        foreach (['ibexa.api.storage_engine.legacy.connection', 'ibexa.persistence.connection'] as $id) {
            $container->setDefinition($id, (new Definition(Connection::class))->setSynthetic(true)->setPublic(true));
        }

        $container->setDefinition('netgen_remote_media.provider', (new Definition(ProviderInterface::class))->setSynthetic(true)->setPublic(true));
        $container->setDefinition('logger', (new Definition(NullLogger::class))->setSynthetic(true)->setPublic(true));

        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../../bundle/Resources/config/services')))->load('richtext.yaml');
        $container->getDefinition(LocationCleaner::class)->setPublic(true);

        $container->compile();

        return $container;
    }
}
