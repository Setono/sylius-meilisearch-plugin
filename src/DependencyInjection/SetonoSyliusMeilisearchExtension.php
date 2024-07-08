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
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\String\u;

final class SetonoSyliusMeilisearchExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{
         *      indexes: array<string, array{document: class-string<Document>, indexer: string|null, entities: list<class-string>, prefix: string|null}>,
         *      server: array{ host: string, master_key: string },
         *      search: array{ enabled: bool, route: string, index: string }
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        // indexes
        $container->setParameter('setono_sylius_meilisearch.indexes', $config['indexes']);

        // server
        $container->setParameter('setono_sylius_meilisearch.server.host', $config['server']['host']);
        $container->setParameter('setono_sylius_meilisearch.server.master_key', $config['server']['master_key']);

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
        self::registerSearchConfiguration($config['search'], array_keys($config['indexes']), $container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'buses' => [
                    'setono_sylius_meilisearch.command_bus' => null,
                ],
            ],
        ]);

        $container->prependExtensionConfig('sylius_ui', [
            'events' => [
                'sylius.shop.layout.header.grid' => [
                    'blocks' => [
                        'search' => [
                            'template' => '@SetonoSyliusMeilisearchPlugin/search/widget.html.twig',
                            'priority' => 20,
                        ],
                    ],
                ],
            ],
        ]);
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

    /**
     * todo the search controller should only be available when search is enabled
     *
     * @param array{ enabled: bool, route: string, index: string } $config the search configuration
     * @param list<string> $indexes a list of index names
     */
    private static function registerSearchConfiguration(array $config, array $indexes, ContainerBuilder $container): void
    {
        if (true === $config['enabled'] && !isset($config['index'])) {
            throw new \RuntimeException('When you enable search you need to provide an index to search');
        }

        if (!in_array($config['index'], $indexes, true)) {
            throw new \RuntimeException(sprintf('For the search configuration you have added the index "%s". That index is not configured in setono_sylius_meilisearch.indexes. Available indexes are [%s]', $config['index'], implode(', ', $indexes)));
        }

        $container->setParameter('setono_sylius_meilisearch.search.enabled', $config['enabled']);
        $container->setParameter('setono_sylius_meilisearch.search.route', $config['route']);
        $container->setParameter('setono_sylius_meilisearch.search.index', $config['index']);
    }
}
