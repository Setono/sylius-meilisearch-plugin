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
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EnabledEntityFilter;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EntityFilterInterface;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\StockAvailableEntityFilter;
use Setono\SyliusMeilisearchPlugin\Form\Builder\FilterFormBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FilterValuesSorterInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
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
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
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
         *      server: array{ url: string, public_url: string|null, master_key: string, search_key: string },
         *      metadata: array{ cache: bool },
         *      search: array{ enabled: bool, path: string, index: string, hits_per_page: int, taxon: array{ path: string } },
         *      autocomplete: array{ enabled: bool, indexes: list<string>, container: string, placeholder: string, limit: int },
         *      resources: array,
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $this->registerResources('setono_sylius_meilisearch', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);

        self::setServerParameters($config['server'], $container);
        self::assertSearchKeyIsNotMasterKey($config['server'], $config['autocomplete']['enabled'], $container);

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

        $container->registerForAutoconfiguration(FilterBuilderInterface::class)
            ->addTag('setono_sylius_meilisearch.filter_builder');

        $container->registerForAutoconfiguration(FilterFormBuilderInterface::class)
            ->addTag('setono_sylius_meilisearch.filter_form_builder');

        $container->registerForAutoconfiguration(FilterValuesSorterInterface::class)
            ->addTag('setono_sylius_meilisearch.filter_values_sorter');

        self::registerIndexesConfiguration($config['indexes'], $container);
        self::registerSearchConfiguration($config['search'], $container, $loader);
        self::registerAutocompleteConfiguration($config['autocomplete'], array_keys($config['indexes']), $container, $loader);
    }

    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration((bool) $container->getParameter('kernel.debug'));
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
            'http_client' => [
                'scoped_clients' => [
                    'http_client.setono_sylius_meilisearch' => [
                        'scope' => '%env(MEILISEARCH_URL)%',
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
                            'template' => '@SetonoSyliusMeilisearchPlugin/search_widget/widget.html.twig',
                            'priority' => 20,
                        ],
                    ],
                ],
                'sylius.shop.layout.javascripts' => [
                    'blocks' => [
                        'setono_sylius_meilisearch_autocomplete_configuration' => [
                            'template' => '@SetonoSyliusMeilisearchPlugin/autocomplete/configuration.html.twig',
                            'priority' => 10,
                        ],
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
     * @param array{url: string, public_url: string|null, master_key: string, search_key: string} $config
     */
    private static function setServerParameters(array $config, ContainerBuilder $container): void
    {
        $url = self::normalizeServerUrl($container, $config['url']);

        // The public URL is the browser-facing host used by the autocomplete widget. In containerized
        // setups the server URL is often an internal hostname that is unreachable from browsers, so it
        // can be configured separately. A null or empty value falls back to the (server-side) URL.
        $publicUrl = $container->resolveEnvPlaceholders($config['public_url'] ?? '', true);
        $publicUrl = is_string($publicUrl) && '' !== $publicUrl
            ? self::normalizeServerUrl($container, (string) $config['public_url'])
            : $url;

        $container->setParameter('setono_sylius_meilisearch.server.url', $url);
        $container->setParameter('setono_sylius_meilisearch.server.public_url', $publicUrl);
        $container->setParameter('setono_sylius_meilisearch.server.master_key', $config['master_key']);
        $container->setParameter('setono_sylius_meilisearch.server.search_key', $config['search_key']);
    }

    /**
     * Resolves environment placeholders in the given URL and normalizes it: a valid host is required
     * and a missing/invalid scheme is coerced to http (e.g. //host or tcp:// are fixed for the user).
     */
    private static function normalizeServerUrl(ContainerBuilder $container, string $url): string
    {
        $resolvedUrl = $container->resolveEnvPlaceholders($url, true);
        if (!is_string($resolvedUrl) || '' === $resolvedUrl) {
            throw new InvalidArgumentException('The Meilisearch URL must be a string. Value given: ' . $url);
        }

        $parts = parse_url($resolvedUrl);
        if (!is_array($parts) || !isset($parts['host'])) {
            throw new InvalidArgumentException(sprintf('The Meilisearch URL must be a valid URL. Value given: %s', $url));
        }

        if (!isset($parts['scheme']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
            $parts['scheme'] = 'http';
        }

        return sprintf(
            '%s://%s%s%s%s',
            $parts['scheme'],
            isset($parts['user'], $parts['pass']) ? sprintf('%s:%s@', $parts['user'], $parts['pass']) : '',
            $parts['host'],
            isset($parts['port']) ? sprintf(':%d', $parts['port']) : '',
            $parts['path'] ?? '',
        );
    }

    /**
     *  @param array<string, array{document: class-string<Document>, entities: list<class-string>, data_provider: class-string, indexer: class-string|null, prefix: string|null, default_filters: array<string, bool>}> $config
     */
    private static function registerIndexesConfiguration(array $config, ContainerBuilder $container): void
    {
        $indexRegistry = $container->getDefinition('setono_sylius_meilisearch.config.index_registry');

        foreach ($config as $indexName => $index) {
            // The "search" index name is reserved; this is validated in Configuration.

            $indexServiceId = self::getIndexServiceId($indexName);

            $indexerServiceId = $index['indexer'] ?? self::registerDefaultIndexer($container, $indexName, $indexServiceId);

            $container->setDefinition($indexServiceId, new Definition(Index::class, [
                $indexName,
                $index['document'],
                $index['entities'],
                ServiceLocatorTagPass::register($container, [
                    IndexableDataProviderInterface::class => new Reference($index['data_provider']),
                    IndexerInterface::class => new Reference($indexerServiceId),
                    IndexUidResolverInterface::class => new Reference(IndexUidResolverInterface::class),
                    MetadataFactoryInterface::class => new Reference(MetadataFactoryInterface::class),
                ]),
                '' === $index['prefix'] ? null : $index['prefix'],
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

        $definition = new Definition(DefaultIndexer::class, [
            new Reference($indexServiceId),
            new Reference('doctrine'),
            new Reference(IndexScopeProviderInterface::class),
            new Reference(IndexUidResolverInterface::class),
            new Reference(DataMapperInterface::class),
            new Reference('serializer'),
            new Reference(Client::class),
            new Reference(EntityFilterInterface::class),
            new Reference('event_dispatcher'),
            new Reference('setono_sylius_meilisearch.command_bus'),
            new Reference('validator'),
            new Reference('logger'),
        ]);
        // Routes the logger to a dedicated "setono_sylius_meilisearch" monolog channel when
        // MonologBundle is installed; falls back to the default logger otherwise.
        $definition->addTag('monolog.logger', ['channel' => 'setono_sylius_meilisearch']);

        $container->setDefinition($indexerServiceId, $definition);

        return $indexerServiceId;
    }

    /**
     * todo the search controller should only be available when search is enabled
     *
     * @param array{ enabled: bool, path: string, index: string, hits_per_page: int, taxon: array{ path: string } } $config the search configuration
     */
    private static function registerSearchConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('setono_sylius_meilisearch.search.enabled', $config['enabled']);
        $container->setParameter('setono_sylius_meilisearch.search.path', $config['path']); // The route that uses this parameter is defined even if search is disabled
        $container->setParameter('setono_sylius_meilisearch.search.taxon.path', $config['taxon']['path']); // The route that uses this parameter is defined even if search is disabled

        if (!$config['enabled']) {
            return;
        }

        // Presence and validity of $config['index'] (when search is enabled) are validated in Configuration.

        $container->setAlias('setono_sylius_meilisearch.index.search', self::getIndexServiceId($config['index']));
        $container->setAlias(Index::class . ' $searchIndex', self::getIndexServiceId($config['index']));

        $container->setParameter('setono_sylius_meilisearch.search.index', $config['index']);
        $container->setParameter('setono_sylius_meilisearch.search.hits_per_page', $config['hits_per_page']);

        $loader->load('services/conditional/search.xml');
    }

    /**
     * @param array{ enabled: bool, indexes: list<string>, container: string, placeholder: string, limit: int } $config
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
        $container->setParameter('setono_sylius_meilisearch.autocomplete.limit', $config['limit']);

        $loader->load('services/conditional/autocomplete.xml');

        $container->getDefinition(AutocompleteRuntime::class)->setArgument(
            '$indexes',
            array_map(
                static fn (string $index) => new Reference(self::getIndexServiceId($index)),
                $config['indexes'],
            ),
        );
    }

    /**
     * The autocomplete widget embeds the search key in the public page source and the browser queries
     * Meilisearch directly with it, so the search key must never equal the master key — otherwise the
     * master key (full read/write/key-management access) would be published to the world. We fail the
     * build here rather than at runtime, so the misconfiguration can never ship in the first place.
     *
     * This only matters when autocomplete is enabled (that is the only thing that exposes the search
     * key to the browser). Keys are resolved from the environment; if they cannot be resolved at compile
     * time (e.g. injected only at runtime) the check is skipped, since we cannot compare unknown values.
     *
     * @param array{ url: string, public_url: string|null, master_key: string, search_key: string } $serverConfig
     */
    private static function assertSearchKeyIsNotMasterKey(array $serverConfig, bool $autocompleteEnabled, ContainerBuilder $container): void
    {
        if (!$autocompleteEnabled) {
            return;
        }

        try {
            $searchKey = $container->resolveEnvPlaceholders($serverConfig['search_key'], true);
            $masterKey = $container->resolveEnvPlaceholders($serverConfig['master_key'], true);
        } catch (EnvNotFoundException) {
            return;
        }

        if (is_string($searchKey) && '' !== $searchKey && $searchKey === $masterKey) {
            throw new InvalidArgumentException('The Meilisearch search key (MEILISEARCH_SEARCH_KEY) is identical to the master key (MEILISEARCH_MASTER_KEY). With autocomplete enabled, the search key is embedded in the public page source and the browser queries Meilisearch directly with it, so this would publish your master key — full read/write/key-management access to the instance — to the world. Configure a dedicated, search-only key for MEILISEARCH_SEARCH_KEY.');
        }
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

        $container->register(sprintf('setono_sylius_meilisearch.filter.entity.enabled.%s', $indexName), EnabledEntityFilter::class)
            ->setArgument('$index', $indexName)
            ->addTag('setono_sylius_meilisearch.entity_filter')
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

        $container->register(sprintf('setono_sylius_meilisearch.filter.entity.stock_available.%s', $indexName), StockAvailableEntityFilter::class)
            ->setArgument('$index', $indexName)
            ->addTag('setono_sylius_meilisearch.entity_filter')
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
