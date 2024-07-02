<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DependencyInjection\Compiler;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Document;
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

        /** @var array<string, array{document: class-string<Document>, indexer: string, entities: list<class-string>, prefix: string}> $indexes */
        $indexes = $container->getParameter('setono_sylius_meilisearch.indexes');

        foreach ($indexes as $indexName => $index) {
            $indexServiceId = sprintf('setono_sylius_meilisearch.index.%s', $indexName);

            $container->setDefinition($indexServiceId, new Definition(Index::class, [
                $indexName,
                $index['document'],
                $index['entities'],
                ServiceLocatorTagPass::register($container, [IndexerInterface::class => new Reference($index['indexer'])]),
                $index['prefix'],
            ]));

            $indexRegistry->addMethodCall('add', [new Reference($indexServiceId)]);

            $indexer = $container->getDefinition($index['indexer']);
            $indexer->setArgument('$index', new Reference($indexServiceId));
        }
    }
}
