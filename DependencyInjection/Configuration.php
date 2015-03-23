<?php

namespace Modera\FileRepositoryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('modera_file_repository');

        $rootNode
            ->children()
                ->scalarNode('is_enabled')
                    ->defaultValue(true)
                ->end()
                ->scalarNode('route_url_prefix')
                    ->defaultValue('/u')
                ->end()
                ->scalarNode('get_file_route')
                    ->defaultValue('modera_file_repository.get_file')
                ->end()
                ->scalarNode('default_url_generator')
                    ->defaultValue('modera_file_repository.stored_file.url_generator')
                ->end()
                ->arrayNode('url_generators')
                    ->prototype('variable')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
