<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection;

use Setono\SyliusMeilisearchPlugin\DataProvider\DefaultIndexableDataProvider;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Form\Type\SynonymType;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
use Setono\SyliusMeilisearchPlugin\Model\Synonym;
use Setono\SyliusMeilisearchPlugin\Repository\SynonymRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_meilisearch');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $this->addResourcesSection($rootNode);

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
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('document')
                                ->info(sprintf('The fully qualified class name for the document that maps to the index. If you are creating a product index, a good starting point is the %s', Product::class))
                                ->cannotBeEmpty()
                                ->isRequired()
                            ->end()
                            ->arrayNode('entities')
                                ->info('The Doctrine entities that make up this index. Examples could be "App\Entity\Product\Product", "App\Entity\Taxonomy\Taxon", etc.')
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('data_provider')
                                ->info('You can set a custom data provider here. If you do not set one, the default data provider will be used.')
                                ->defaultValue(DefaultIndexableDataProvider::class)
                            ->end()
                            ->scalarNode('indexer')
                                ->info(sprintf('You can set a custom indexer here. If you do not set one, the default indexer will be used. The default indexer is %s', DefaultIndexer::class))
                                ->cannotBeEmpty()
                                ->defaultNull()
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
                        ->scalarNode('path')
                            ->defaultValue('search')
                            ->info('This is the path where searches are displayed')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('hits_per_page')
                            ->defaultValue(60) // 60 is a good number for product lists, because it is divisible by 2, 3, 4, 5, and 6
                            ->info('The number of hits per page')
                            ->min(1)
                        ->end()
                        ->scalarNode('index')
                            ->info('The index to search (must be configured in setono_sylius_meilisearch.indexes)')
                            ->isRequired()
                            ->cannotBeEmpty()
        ;

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        /**
         * @psalm-suppress MixedMethodCall,PossiblyUndefinedMethod,PossiblyNullReference,UndefinedInterfaceMethod
         */
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('synonym')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Synonym::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(SynonymRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(SynonymType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }
}
