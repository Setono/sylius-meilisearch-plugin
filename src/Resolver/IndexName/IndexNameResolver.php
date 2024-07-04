<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexName;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;

/**
 * This is a default index name resolver. This will give developers a better experience for simple scenarios
 * where an index name like 'products' or 'taxons' or 'pages' will suffice.
 *
 * An example of a resolved index name could be 'prod__products__fashion_web__en_us__usd'
 */
final class IndexNameResolver implements IndexNameResolverInterface
{
    public function __construct(
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly string $environment,
        private readonly string $separator = '__',
    ) {
    }

    public function resolve(Index $index): string
    {
        return $this->resolveFromIndexScope($this->indexScopeProvider->getFromContext($index));
    }

    public function resolveFromIndexScope(IndexScope $indexScope): string
    {
        return strtolower(implode($this->separator, array_filter([
            $indexScope->index->prefix,
            $this->environment,
            $indexScope->index->name,
            $indexScope->channelCode,
            $indexScope->localeCode,
            $indexScope->currencyCode,
        ])));
    }
}
