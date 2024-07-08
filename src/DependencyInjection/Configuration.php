<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection;

use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
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
                                ->info(sprintf('You can set a custom indexer here. If you do not set one, the default indexer will be used. The default indexer is %s', DefaultIndexer::class))
                                ->defaultNull()
                            ->end()
                            ->arrayNode('entities')
                                ->info('The Doctrine entities that make up this index. Examples could be "App\Entity\Product\Product", "App\Entity\Taxonomy\Taxon", etc.')
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
                ->arrayNode('server')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('host')
                            ->info('This is the host of the Meilisearch instance')
                            ->defaultValue('%env(MEILISEARCH_HOST)%')
                            ->cannotBeEmpty()
                        ->end()
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
                        ->scalarNode('route')
                            ->defaultValue('search')
                            ->info('This is the path where searches are displayed')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('index')
                            ->info('The index to search (must be configured in setono_sylius_meilisearch.indexes)')
                            ->cannotBeEmpty()
        ;

        return $treeBuilder;
    }
}
