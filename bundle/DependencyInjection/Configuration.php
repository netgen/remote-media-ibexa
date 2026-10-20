<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\Configuration as SiteAccessConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

use function array_is_list;
use function is_array;
use function is_string;

final class Configuration extends SiteAccessConfiguration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('netgen_remote_media_ibexa');

        // Settings deliberately have no defaults in the tree (defaults are in default_settings.yaml),
        // so a scope only overrides the settings it defines.
        $this->generateScopeBaseNode($treeBuilder->getRootNode())
            ->arrayNode('richtext')
                ->info('Remote media element in the RichText (CKEditor 5) field')
                ->children()
                    ->scalarNode('variation_group')
                        ->info('Variation group used for cropping in the editor and for rendering')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('location_source')
                        ->info('Source stored on remote resource locations created from RichText')
                    ->end()
                    ->variableNode('allowed_types')
                        ->validate()
                            ->ifTrue(static fn (mixed $value): bool => !self::isStringList($value))
                            ->thenInvalid('Expected a list of strings, got %s')
                        ->end()
                    ->end()
                    ->variableNode('allowed_visibilities')
                        ->validate()
                            ->ifTrue(static fn (mixed $value): bool => !self::isStringList($value))
                            ->thenInvalid('Expected a list of strings, got %s')
                        ->end()
                    ->end()
                    ->variableNode('allowed_tags')
                        ->validate()
                            ->ifTrue(static fn (mixed $value): bool => !self::isStringList($value))
                            ->thenInvalid('Expected a list of strings, got %s')
                        ->end()
                    ->end()
                    ->booleanNode('disable_upload')
                        ->info('Upload is also disabled for users without the ngrm/upload policy')
                    ->end()
                    ->scalarNode('folder')
                        ->info('Folder path for browsing and uploading, null for no restriction')
                    ->end()
                    ->scalarNode('parent_folder')
                        ->info('Parent folder path for browsing and uploading, null for no restriction')
                    ->end()
                    ->variableNode('upload_context')
                        ->validate()
                            ->ifTrue(static fn (mixed $value): bool => !is_array($value))
                            ->thenInvalid('Expected a map of context keys to values, got %s')
                        ->end()
                    ->end()
                ->end()
            ->end();

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
