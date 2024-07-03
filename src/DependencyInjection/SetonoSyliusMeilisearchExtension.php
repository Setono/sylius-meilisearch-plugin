<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection;

use Meilisearch\Client;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Filter\Doctrine\FilterInterface as DoctrineFilterInterface;
use Setono\SyliusMeilisearchPlugin\Filter\Object\FilterInterface as ObjectFilterInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\UrlGenerator\EntityUrlGeneratorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\String\u;

final class SetonoSyliusMeilisearchExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{
         *      indexes: array<string, array{document: class-string<Document>, indexer: string|null, entities: list<class-string>, prefix: string|null}>,
         *      server: array{ host: string, master_key: string },
         *      search: array{ enabled: bool, indexes: list<string> },
         *      routes: array{ search: string }
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        // indexes
        $container->setParameter('setono_sylius_meilisearch.indexes', $config['indexes']);

        // server
        $container->setParameter('setono_sylius_meilisearch.server.host', $config['server']['host']);
        $container->setParameter('setono_sylius_meilisearch.server.master_key', $config['server']['master_key']);

        // search
        if (true === $config['search']['enabled'] && [] === $config['search']['indexes']) {
            throw new \RuntimeException('When you enable search you need to provide at least one index to search');
        }
        foreach ($config['search']['indexes'] as $index) {
            if (!isset($config['indexes'][$index])) {
                throw new \RuntimeException(sprintf('For the search configuration you have added the index "%s". That index is not configured in setono_sylius_meilisearch.indexes.', $index));
            }
        }
        $container->setParameter('setono_sylius_meilisearch.search.enabled', $config['search']['enabled']);
        $container->setParameter('setono_sylius_meilisearch.search.indexes', $config['search']['indexes']);

        // routes
        $container->setParameter('setono_sylius_meilisearch.routes.search', $config['routes']['search']);

        $loader->load('services.xml');

        // auto configuration
        $container->registerForAutoconfiguration(DataMapperInterface::class)
            ->addTag('setono_sylius_meilisearch.data_mapper');

        $container->registerForAutoconfiguration(DoctrineFilterInterface::class)
            ->addTag('setono_sylius_meilisearch.doctrine_filter');

        $container->registerForAutoconfiguration(IndexScopeProviderInterface::class)
            ->addTag('setono_sylius_meilisearch.index_scope_provider');

        $container->registerForAutoconfiguration(ObjectFilterInterface::class)
            ->addTag('setono_sylius_meilisearch.object_filter');

        $container->registerForAutoconfiguration(EntityUrlGeneratorInterface::class)
            ->addTag('setono_sylius_meilisearch.url_generator');

        self::registerIndexesConfiguration($config['indexes'], $container);
    }

    /**
     *  @param array<string, array{document: class-string<Document>, indexer: string|null, entities: list<class-string>, prefix: string|null}> $config
     */
    private static function registerIndexesConfiguration(array $config, ContainerBuilder $container): void
    {
        $indexRegistry = $container->getDefinition('setono_sylius_meilisearch.config.index_registry');

        foreach ($config as $indexName => $index) {
            $indexServiceId = sprintf('setono_sylius_meilisearch.index.%s', $indexName);

            $indexerServiceId = $index['indexer'] ?? self::registerDefaultIndexer($container, $indexName, $indexServiceId);

            $container->setDefinition($indexServiceId, new Definition(Index::class, [
                $indexName,
                $index['document'],
                $index['entities'],
                ServiceLocatorTagPass::register($container, [IndexerInterface::class => new Reference($indexerServiceId)]),
                $index['prefix'],
            ]));

            $indexRegistry->addMethodCall('add', [new Reference($indexServiceId)]);

            $container->setAlias(sprintf(Index::class . ' $%s', u($indexName)->camel()), $indexServiceId);
            $container->setAlias(sprintf(IndexerInterface::class . ' $%sIndexer', u($indexName)->camel()), $indexServiceId);
        }
    }

    private static function registerDefaultIndexer(ContainerBuilder $container, string $indexName, string $indexServiceId): string
    {
        $indexerServiceId = sprintf('setono_sylius_meilisearch.indexer.%s', $indexName);

        $container->setDefinition($indexerServiceId, new Definition(DefaultIndexer::class, [
            new Reference($indexServiceId),
            new Reference('doctrine'),
            new Reference('setono_sylius_meilisearch.provider.index_scope.composite'),
            new Reference('setono_sylius_meilisearch.resolver.index_name'),
            new Reference(DataMapperInterface::class),
            new Reference('serializer'),
            new Reference(Client::class),
            new Reference('setono_sylius_meilisearch.filter.object.composite'),
        ]));

        return $indexerServiceId;
    }
}
