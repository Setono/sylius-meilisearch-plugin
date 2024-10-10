<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection;

use Meilisearch\Client;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataProvider\IndexableDataProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\CachedMetadataFactory;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactory;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableDataFilter\ChannelsAwareFilter;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableDataFilter\EnabledFilter;
use Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableDataFilter\StockAvailableFilter;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\ChannelsAwareEntityFilter;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EntityFilterInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;
use Setono\SyliusMeilisearchPlugin\Twig\AutocompleteRuntime;
use Setono\SyliusMeilisearchPlugin\UrlGenerator\EntityUrlGeneratorInterface;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
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
         *      indexes: array<string, array{document: class-string<Document>, entities: list<class-string>, data_provider: class-string, indexer: class-string|null, prefix: string|null, default_filters: array<string, bool>}>,
         *      server: array{ host: string, master_key: string, search_key: string },
         *      metadata: array{ cache: bool },
         *      search: array{ enabled: bool, path: string, index: string, hits_per_page: int },
         *      autocomplete: array{ enabled: bool, indexes: list<string>, container: string, placeholder: string },
         *      resources: array,
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $this->registerResources('setono_sylius_meilisearch', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);

        // server
        $container->setParameter('setono_sylius_meilisearch.server.host', $config['server']['host']);
        $container->setParameter('setono_sylius_meilisearch.server.master_key', $config['server']['master_key']);
        $container->setParameter('setono_sylius_meilisearch.server.search_key', $config['server']['search_key']);

        // cache
        $metadataCacheEnabled = $config['metadata']['cache'];
        $container->setParameter('setono_sylius_meilisearch.cache', $metadataCacheEnabled);
        if ($metadataCacheEnabled) {
            $this->registerCachedMetadataFactory($container);
        }

        $loader->load('services.xml');

        // auto configuration
        $container->registerForAutoconfiguration(DataMapperInterface::class)
            ->addTag('setono_sylius_meilisearch.data_mapper');

        $container->registerForAutoconfiguration(IndexScopeProviderInterface::class)
            ->addTag('setono_sylius_meilisearch.index_scope_provider');

        $container->registerForAutoconfiguration(EntityFilterInterface::class)
            ->addTag('setono_sylius_meilisearch.entity_filter');

        $container->registerForAutoconfiguration(EntityUrlGeneratorInterface::class)
            ->addTag('setono_sylius_meilisearch.url_generator');

        self::registerIndexesConfiguration($config['indexes'], $container);
        self::registerSearchConfiguration($config['search'], array_keys($config['indexes']), $container, $loader);
        self::registerAutocompleteConfiguration($config['autocomplete'], array_keys($config['indexes']), $container, $loader);
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
            'templates' => [
                'filter' => [
                    'indexes' => '@SetonoSyliusMeilisearchPlugin/admin/grid/filter/indexes.html.twig',
                ],
            ],
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
                        'enabled' => [
                            'type' => 'twig',
                            'label' => 'sylius.ui.enabled',
                            'options' => [
                                'template' => '@SyliusUi/Grid/Field/enabled.html.twig',
                            ],
                        ],
                        'indexes' => [
                            'type' => 'twig',
                            'label' => 'setono_sylius_meilisearch.ui.indexes',
                            'path' => '.',
                            'options' => [
                                'template' => '@SetonoSyliusMeilisearchPlugin/admin/grid/field/_indexes.html.twig',
                            ],
                        ],
                        'locale' => [
                            'type' => 'string',
                            'label' => 'sylius.ui.locale',
                        ],
                        'channels' => [
                            'type' => 'twig',
                            'label' => 'sylius.ui.channels',
                            'options' => [
                                'template' => '@SyliusAdmin/Grid/Field/_channels.html.twig',
                            ],
                        ],
                    ],
                    'filters' => [
                        'search' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_meilisearch.ui.search_term_and_synonym',
                            'options' => [
                                'fields' => ['term', 'synonym'],
                            ],
                        ],
                        'enabled' => [
                            'type' => 'boolean',
                            'label' => 'sylius.ui.enabled',
                        ],
                        'indexes' => [
                            'type' => 'indexes',
                            'label' => 'setono_sylius_meilisearch.ui.indexes',
                            'form_options' => [
                                'placeholder' => 'sylius.ui.all',
                            ],
                        ],
                        'locale' => [
                            'type' => 'entity',
                            'label' => 'sylius.ui.locale',
                            'form_options' => [
                                'class' => '%sylius.model.locale.class%',
                            ],
                        ],
                        'channel' => [
                            'type' => 'entities',
                            'label' => 'sylius.ui.channel',
                            'form_options' => [
                                'class' => '%sylius.model.channel.class%',
                            ],
                            'options' => [
                                'field' => 'channels.id',
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
                        // todo in the future it might be a good user experience if we had bulk actions for adding indexes and channels to synonyms
                        'bulk' => [
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
                        'setono_sylius_meilisearch_search' => [
                            'template' => '@SetonoSyliusMeilisearchPlugin/search/widget.html.twig',
                            'priority' => 20,
                        ],
                    ],
                ],
                'sylius.shop.layout.javascripts' => [
                    'blocks' => [
                        'setono_sylius_meilisearch_autocomplete' => [
                            'template' => '@SetonoSyliusMeilisearchPlugin/autocomplete/javascript.html.twig',
                        ],
                    ],
                ],
                'sylius.shop.layout.stylesheets' => [
                    'blocks' => [
                        'setono_sylius_meilisearch_styles' => [
                            'template' => '@SetonoSyliusMeilisearchPlugin/autocomplete/styles.html.twig',
                            'priority' => -20,
                        ],
                    ],
                ],
                'sylius.shop.layout.after_body' => [
                    'blocks' => [
                        'setono_sylius_meilisearch_loader' => [
                            'template' => '@SetonoSyliusMeilisearchPlugin/loader.html.twig',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     *  @param array<string, array{document: class-string<Document>, entities: list<class-string>, data_provider: class-string, indexer: class-string|null, prefix: string|null, default_filters: array<string, bool>}> $config
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
                ServiceLocatorTagPass::register($container, [
                    IndexableDataProviderInterface::class => new Reference($index['data_provider']),
                    IndexerInterface::class => new Reference($indexerServiceId),
                    IndexNameResolverInterface::class => new Reference('setono_sylius_meilisearch.resolver.index_name'),
                    MetadataFactoryInterface::class => new Reference(MetadataFactoryInterface::class),
                ]),
                $index['prefix'],
            ]));

            $indexRegistry->addMethodCall('add', [new Reference($indexServiceId)]);

            $container->setAlias(sprintf(Index::class . ' $%s', u($indexName)->camel()), $indexServiceId);
            $container->setAlias(sprintf(IndexerInterface::class . ' $%sIndexer', u($indexName)->camel()), $indexServiceId);

            self::registerFilters($container, $indexName, $index['entities'], $index['default_filters'] ?? []);
        }
    }

    private static function registerDefaultIndexer(ContainerBuilder $container, string $indexName, string $indexServiceId): string
    {
        $indexerServiceId = sprintf('setono_sylius_meilisearch.indexer.%s', $indexName);

        $container->setDefinition($indexerServiceId, new Definition(DefaultIndexer::class, [
            new Reference($indexServiceId),
            new Reference('doctrine'),
            new Reference('Setono\SyliusMeilisearchPlugin\Provider\IndexScope\CompositeIndexScopeProvider'),
            new Reference('setono_sylius_meilisearch.resolver.index_name'),
            new Reference(DataMapperInterface::class),
            new Reference('serializer'),
            new Reference(Client::class),
            new Reference(EntityFilterInterface::class),
            new Reference('event_dispatcher'),
            new Reference('setono_sylius_meilisearch.command_bus'),
        ]));

        return $indexerServiceId;
    }

    /**
     * todo the search controller should only be available when search is enabled
     *
     * @param array{ enabled: bool, path: string, index: string, hits_per_page: int } $config the search configuration
     * @param list<string> $indexes a list of index names
     */
    private static function registerSearchConfiguration(array $config, array $indexes, ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('setono_sylius_meilisearch.search.enabled', $config['enabled']);
        $container->setParameter('setono_sylius_meilisearch.search.path', $config['path']); // The route that uses this parameter is defined even if search is disabled

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

        $container->setParameter('setono_sylius_meilisearch.search.index', $config['index']);
        $container->setParameter('setono_sylius_meilisearch.search.hits_per_page', $config['hits_per_page']);

        $loader->load('services/conditional/search.xml');
    }

    /**
     * @param array{ enabled: bool, indexes: list<string>, container: string, placeholder: string } $config
     * @param list<string> $indexes a list of configured index names
     */
    private static function registerAutocompleteConfiguration(array $config, array $indexes, ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('setono_sylius_meilisearch.autocomplete.enabled', $config['enabled']);

        if (!$config['enabled']) {
            return;
        }

        if ([] === $config['indexes']) {
            throw new \RuntimeException('You have to configure at least one index for the autocomplete');
        }

        foreach ($config['indexes'] as $index) {
            if (!in_array($index, $indexes, true)) {
                throw new \RuntimeException(sprintf('For the autocomplete configuration you have added the index "%s". That index is not configured in setono_sylius_meilisearch.indexes. Available indexes are [%s]', $index, implode(', ', $indexes)));
            }
        }

        $container->setParameter('setono_sylius_meilisearch.autocomplete.container', $config['container']);
        $container->setParameter('setono_sylius_meilisearch.autocomplete.placeholder', $config['placeholder']);

        $loader->load('services/conditional/autocomplete.xml');

        $container->getDefinition(AutocompleteRuntime::class)->setArgument(
            '$indexes',
            array_map(
                static fn (string $index) => new Reference(self::getIndexServiceId($index)),
                $config['indexes'],
            ),
        );
    }

    private static function getIndexServiceId(string $indexName): string
    {
        return sprintf('setono_sylius_meilisearch.index.%s', $indexName);
    }

    /**
     * @param list<class-string> $entities
     * @param array<string, bool> $defaultFilters
     */
    private static function registerFilters(ContainerBuilder $container, string $indexName, array $entities, array $defaultFilters): void
    {
        $defaultFilters = array_merge([
            'enabled' => true,
            'channels_aware' => true,
            'stock_available' => false,
        ], $defaultFilters);

        foreach ($entities as $entity) {
            if ($defaultFilters['enabled']) {
                self::registerEnabledFilter($container, $indexName, $entity);
            }

            if ($defaultFilters['channels_aware']) {
                self::registerChannelsAwareFilter($container, $indexName, $entity);
            }

            if ($defaultFilters['stock_available']) {
                self::registerStockAvailableFilter($container, $indexName, $entity);
            }
        }
    }

    /**
     * @param class-string $entity
     */
    private static function registerEnabledFilter(ContainerBuilder $container, string $indexName, string $entity): void
    {
        if (!is_a($entity, ToggleableInterface::class, true)) {
            return;
        }

        $container->register(sprintf('setono_sylius_meilisearch.event_subscriber.indexable_data_filter.enabled.%s', $indexName), EnabledFilter::class)
            ->setArgument('$index', $indexName)
            ->addTag('kernel.event_subscriber')
        ;
    }

    /**
     * @param class-string $entity
     */
    private static function registerChannelsAwareFilter(ContainerBuilder $container, string $indexName, string $entity): void
    {
        if (!is_a($entity, ChannelsAwareInterface::class, true)) {
            return;
        }

        $container->register(sprintf('setono_sylius_meilisearch.event_subscriber.indexable_data_filter.channels_aware.%s', $indexName), ChannelsAwareFilter::class)
            ->setArgument('$index', $indexName)
            ->addTag('kernel.event_subscriber')
        ;

        $container->register(sprintf('setono_sylius_meilisearch.filter.entity.channels_aware.%s', $indexName), ChannelsAwareEntityFilter::class)
            ->setArgument('$index', $indexName)
            ->addTag('setono_sylius_meilisearch.entity_filter')
        ;
    }

    /**
     * @param class-string $entity
     */
    private static function registerStockAvailableFilter(ContainerBuilder $container, string $indexName, string $entity): void
    {
        if (!is_a($entity, ProductInterface::class, true)) {
            return;
        }

        $container->register(sprintf('setono_sylius_meilisearch.event_subscriber.indexable_data_filter.stock_available.%s', $indexName), StockAvailableFilter::class)
            ->setArgument('$index', $indexName)
            ->addTag('kernel.event_subscriber')
        ;
    }

    private function registerCachedMetadataFactory(ContainerBuilder $container): void
    {
        $container
            ->register(CachedMetadataFactory::class)
            ->setDecoratedService(MetadataFactory::class)
            ->setArguments([
                new Reference(CachedMetadataFactory::class . '.inner'),
                new Reference('setono_sylius_meilisearch.cache.metadata'),
            ])
        ;

        $container->setAlias(MetadataFactoryInterface::class, CachedMetadataFactory::class);
    }
}
