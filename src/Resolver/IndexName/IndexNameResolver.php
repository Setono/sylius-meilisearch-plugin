<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexName;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * This is a default index name resolver. This will give developers a better experience for simple scenarios
 * where an index name like 'products' or 'taxons' or 'pages' will suffice
 */
final class IndexNameResolver implements IndexNameResolverInterface
{
    public function __construct(
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly string $environment,
    ) {
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

    /**
     * @param class-string|ResourceInterface|Index $value
     */
    private function resolveIndexScope($value): IndexScope
    {
        if (!$value instanceof Index) {
            $value = $this->indexRegistry->getByEntity($value);
        }

        return $this->indexScopeProvider->getFromContext($value);
    }
}
