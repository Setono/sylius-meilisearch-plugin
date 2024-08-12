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
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\String\u;

final class SetonoSyliusMeilisearchExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{
         *      indexes: array<string, array{document: class-string<Document>, indexer: string|null, entities: list<class-string>, prefix: string|null}>,
         *      server: array{ host: string, master_key: string },
         *      search: array{ enabled: bool, path: string, index: string, hits_per_page: integer },
         *      resources: array,
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $this->registerResources('setono_sylius_meilisearch', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);

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
        self::registerSearchConfiguration($config['search'], array_keys($config['indexes']), $container, $loader);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    'setono_sylius_meilisearch.cache.metadata' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
            'messenger' => [
                'buses' => [
                    'setono_sylius_meilisearch.command_bus' => null,
                ],
            ],
        ]);

        $container->prependExtensionConfig('sylius_grid', [
            'grids' => [
                'setono_sylius_meilisearch_admin_synonym' => [
                    'driver' => [
                        'name' => SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
                        'options' => [
                            'class' => '%setono_sylius_meilisearch.model.synonym.class%',
                        ],
                    ],
                    'limits' => [100, 250, 500, 1000],
                    'fields' => [
                        'term' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_meilisearch.ui.term',
                        ],
                        'synonym' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_meilisearch.ui.synonym',
                        ],
                        'locale' => [
                            'type' => 'string',
                            'label' => 'sylius.ui.locale',
                        ],
                        'channel' => [
                            'type' => 'string',
                            'label' => 'sylius.ui.channel',
                        ],
                    ],
                    'filters' => [
                        'search' => [
                            'type' => 'string',
                            'label' => 'sylius.ui.search',
                            'options' => [
                                'fields' => ['term', 'synonym'],
                            ],
                        ],
                    ],
                    'actions' => [
                        'main' => [
                            'create' => [
                                'type' => 'create',
                            ],
                        ],
                        'item' => [
                            'update' => [
                                'type' => 'update',
                            ],
                            'delete' => [
                                'type' => 'delete',
                            ],
                        ],
                    ],
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
            if ('search' === $indexName) {
                throw new \RuntimeException('You cannot use "search" as an index name. It is reserved for the search configuration');
            }

            $indexServiceId = self::getIndexServiceId($indexName);

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
            new Reference('event_dispatcher'),
        ]));

        return $indexerServiceId;
    }

    /**
     * todo the search controller should only be available when search is enabled
     *
     * @param array{ enabled: bool, path: string, index: string, hits_per_page: integer } $config the search configuration
     * @param list<string> $indexes a list of index names
     */
    private static function registerSearchConfiguration(array $config, array $indexes, ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('setono_sylius_meilisearch.search.enabled', $config['enabled']);

        if (!$config['enabled']) {
            return;
        }

        if (!isset($config['index'])) {
            throw new \RuntimeException('When search is enabled, you have to configure the index for the search');
        }

        if (!in_array($config['index'], $indexes, true)) {
            throw new \RuntimeException(sprintf('For the search configuration you have added the index "%s". That index is not configured in setono_sylius_meilisearch.indexes. Available indexes are [%s]', $config['index'], implode(', ', $indexes)));
        }

        $container->setAlias('setono_sylius_meilisearch.index.search', self::getIndexServiceId($config['index']));
        $container->setAlias(Index::class . ' $searchIndex', self::getIndexServiceId($config['index']));

        $container->setParameter('setono_sylius_meilisearch.search.path', $config['path']);
        $container->setParameter('setono_sylius_meilisearch.search.index', $config['index']);
        $container->setParameter('setono_sylius_meilisearch.search.hits_per_page', $config['hits_per_page']);

        $loader->load('services/conditional/search.xml');
    }

    private static function getIndexServiceId(string $indexName): string
    {
        return sprintf('setono_sylius_meilisearch.index.%s', $indexName);
    }
}
