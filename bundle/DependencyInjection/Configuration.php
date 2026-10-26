<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\Configuration as SiteAccessConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

use function array_is_list;
use function is_array;
use function is_string;

final class Configuration extends SiteAccessConfiguration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('netgen_remote_media_ibexa');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @var NodeBuilder $scopeNode */
        $scopeNode = $this->generateScopeBaseNode($rootNode);

        // Settings deliberately have no defaults in the tree (defaults are in default_settings.yaml),
        // so a scope only overrides the settings it defines.
        $richTextNode = $scopeNode
            ->arrayNode('richtext')
                ->info('Remote media element in the RichText (CKEditor 5) field')
                ->children();

        $richTextNode
            ->scalarNode('variation_group')
                ->info('Variation group used for cropping in the editor and for rendering')
                ->cannotBeEmpty();

        $richTextNode
            ->scalarNode('location_source')
                ->info('Source stored on remote resource locations created from RichText');

        foreach (['allowed_types', 'allowed_visibilities', 'allowed_tags'] as $name) {
            $richTextNode
                ->variableNode($name)
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !self::isStringList($value))
                        ->thenInvalid('Expected a list of strings, got %s');
        }

        $richTextNode
            ->booleanNode('disable_upload')
                ->info('Upload is also disabled for users without the ngrm/upload policy');

        $richTextNode
            ->scalarNode('folder')
                ->info('Folder path for browsing and uploading, null for no restriction');

        $richTextNode
            ->scalarNode('parent_folder')
                ->info('Parent folder path for browsing and uploading, null for no restriction');

        $richTextNode
            ->variableNode('upload_context')
                ->validate()
                    ->ifTrue(static fn (mixed $value): bool => !is_array($value))
                    ->thenInvalid('Expected a map of context keys to values, got %s');

        return $treeBuilder;
    }

    private static function isStringList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
