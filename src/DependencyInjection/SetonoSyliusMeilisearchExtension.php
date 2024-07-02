<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection;

use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Filter\Doctrine\FilterInterface as DoctrineFilterInterface;
use Setono\SyliusMeilisearchPlugin\Filter\Object\FilterInterface as ObjectFilterInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\UrlGenerator\EntityUrlGeneratorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusMeilisearchExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{
         *      indexes: array<string, mixed>,
         *      credentials: array{ master_key: string },
         *      search: array{ enabled: bool, indexes: list<string> },
         *      routes: array{ search: string }
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        // indexes
        $container->setParameter('setono_sylius_meilisearch.indexes', $config['indexes']);

        // credentials
        $container->setParameter('setono_sylius_meilisearch.credentials.master_key', $config['credentials']['master_key']);

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

        $container->registerForAutoconfiguration(IndexerInterface::class)
            ->addTag('setono_sylius_meilisearch.indexer');
    }
}
