<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection;

use Setono\SyliusMeilisearchPlugin\Document\Product;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_meilisearch');
        $rootNode = $treeBuilder->getRootNode();

        /** @psalm-suppress MixedMethodCall,UndefinedMethod,PossiblyUndefinedMethod,PossiblyNullReference */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('indexes')
                    ->info('Define the indexes you want to create. All based on a document class you define')
                    ->useAttributeAsKey('name')
                    ->beforeNormalization()->castToArray()->end()
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('document')
                                ->info(sprintf('The fully qualified class name for the document that maps to the index. If you are creating a product index, a good starting point is the %s', Product::class))
                                ->cannotBeEmpty()
                                ->isRequired()
                            ->end()
                            ->scalarNode('indexer')
                                ->info('This is the service id of the indexer that will be used to index resources on this index')
                                ->cannotBeEmpty()
                                ->defaultValue('setono_sylius_meilisearch.indexer.default')
                            ->end()
                            ->arrayNode('resources')
                                ->info('The Sylius resources that make up this index. Examples could be "sylius.product", "sylius.taxon", etc.')
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('prefix')
                                ->defaultNull()
                                ->info('If you want to prepend a string to the index name, you can set it here. This can be useful in a development setup where each developer has their own prefix. Notice that the environment is already prefixed by default, so you do not have to prefix that.')
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('credentials')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('master_key')
                            ->info('This is the master key for the Meilisearch instance')
                            ->defaultValue('%env(MEILISEARCH_MASTER_KEY)%')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('search')
                    ->canBeEnabled()
                    ->info('Configures your site search (and autocomplete) experience')
                    ->children()
                        ->arrayNode('indexes')
                            ->requiresAtLeastOneElement()
                            ->info('The indexes to search (must be configured in setono_sylius_meilisearch.indexes). Please notice that if you enable search you MUST provide at least one index to search.')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('search')
                            ->defaultValue('/search')
                            ->info('This is the path where searches are displayed')
                            ->cannotBeEmpty()
        ;

        return $treeBuilder;
    }
}
