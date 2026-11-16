<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Tests\DependencyInjection;

use Netgen\Bundle\RemoteMediaIbexaBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testFullConfiguration(): void
    {
        $richText = [
            'variation_group' => 'article',
            'location_source' => 'richtext',
            'allowed_types' => ['image', 'video'],
            'allowed_visibilities' => ['public'],
            'allowed_tags' => ['news'],
            'disable_upload' => true,
            'folder' => 'media/articles',
            'parent_folder' => 'media',
            'upload_context' => ['source' => 'ibexa'],
        ];

        self::assertSame(
            ['system' => ['default' => ['richtext' => $richText]]],
            $this->process(['default' => ['richtext' => $richText]]),
        );
    }

    public function testScopeKeepsOnlyDefinedSettings(): void
    {
        // No defaults in the tree, so a scope does not override settings of less specific scopes
        self::assertSame(
            ['system' => ['site_group' => ['richtext' => ['folder' => 'media']]]],
            $this->process(['site_group' => ['richtext' => ['folder' => 'media']]]),
        );
    }

    public function testNullFolders(): void
    {
        $config = $this->process(['default' => ['richtext' => ['folder' => null, 'parent_folder' => null, 'location_source' => null]]]);

        self::assertSame(
            ['folder' => null, 'parent_folder' => null, 'location_source' => null],
            $config['system']['default']['richtext'],
        );
    }

    /**
     * @param array<string, mixed> $richText
     */
    #[DataProvider('invalidConfigurationDataProvider')]
    public function testInvalidConfiguration(array $richText): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['default' => ['richtext' => $richText]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationDataProvider(): iterable
    {
        return [
            'numeric folder' => [['folder' => 2024]],
            'numeric parent folder' => [['parent_folder' => 2024]],
            'boolean location source' => [['location_source' => true]],
            'numeric variation group' => [['variation_group' => 1]],
            'empty variation group' => [['variation_group' => '']],
            'allowed types map' => [['allowed_types' => ['image' => 'image']]],
            'allowed tags with non-string' => [['allowed_tags' => ['news', 1]]],
            'allowed visibilities string' => [['allowed_visibilities' => 'public']],
            'upload context string' => [['upload_context' => 'source=ibexa']],
            'unknown setting' => [['unknown' => true]],
        ];
    }

    /**
     * @param array<string, mixed> $system
     *
     * @return array<string, mixed>
     */
    private function process(array $system): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [['system' => $system]]);
    }
}
