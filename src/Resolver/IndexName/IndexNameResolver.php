<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexName;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * This is a default index name resolver. This will give developers a better experience for simple scenarios
 * where an index name like 'products' or 'taxons' or 'pages' will suffice
 */
final class IndexNameResolver implements IndexNameResolverInterface
{
    private IndexRegistry $indexRegistry;

    private IndexScopeProviderInterface $indexScopeProvider;

    private string $environment;

    public function __construct(
        IndexRegistry $indexRegistry,
        IndexScopeProviderInterface $indexScopeProvider,
        string $environment,
    ) {
        $this->indexRegistry = $indexRegistry;
        $this->indexScopeProvider = $indexScopeProvider;
        $this->environment = $environment;
    }

    public function resolve($resource): string
    {
        return $this->resolveFromIndexScope($this->resolveIndexScope($resource));
    }

    public function resolveFromIndexScope(IndexScope $indexScope): string
    {
        return strtolower(implode('__', array_filter([
            $indexScope->index->prefix,
            $this->environment,
            $indexScope->index->name,
            $indexScope->channelCode,
            $indexScope->localeCode,
            $indexScope->currencyCode,
        ])));
    }

    public function supports(Index $index): bool
    {
        return true;
    }

    /**
     * @param class-string|ResourceInterface|Index $value
     */
    private function resolveIndexScope($value): IndexScope
    {
        if (!$value instanceof Index) {
            $value = $this->indexRegistry->getByResourceClass($value);
        }

        return $this->indexScopeProvider->getFromContext($value);
    }
}
