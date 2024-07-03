<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection\Compiler;

use Meilisearch\Client;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterIndexesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('setono_sylius_meilisearch.config.index_registry') || !$container->hasParameter('setono_sylius_meilisearch.indexes')) {
            return;
        }

        $indexRegistry = $container->getDefinition('setono_sylius_meilisearch.config.index_registry');

        /** @var array<string, array{document: class-string<Document>, indexer: string|null, entities: list<class-string>, prefix: string|null}> $indexes */
        $indexes = $container->getParameter('setono_sylius_meilisearch.indexes');

        foreach ($indexes as $indexName => $index) {
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
            new Reference('setono_sylius_meilisearch.data_mapper.composite'),
            new Reference('serializer'),
            new Reference(Client::class),
            new Reference('setono_sylius_meilisearch.filter.object.composite'),
        ]));

        return $indexerServiceId;
    }
}
