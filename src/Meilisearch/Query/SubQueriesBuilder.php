<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FilterBuilderInterface;

final class SubQueriesBuilder implements SubQueriesBuilderInterface
{
    public function __construct(
        private readonly FilterBuilderInterface $filterBuilder,
        private readonly SearchQueryBuilder $searchQueryBuilder,
    ) {
    }

    public function build(string $indexName, string $query, array $facets, array $facetsNames, array $filters): array
    {
        $searchQueries = [];

        foreach ($facetsNames as $facet) {
            $facetsNames = [$facet];
            /** @var array<string, mixed> $filteredFacets */
            $filteredFacets = array_filter($filters, static fn ($value) => $value !== $facet, \ARRAY_FILTER_USE_KEY);
            /** @var array<string, mixed> $filter */
            $filter = $this->filterBuilder->build($facets, $filteredFacets);

            $searchQueries[] = $this->searchQueryBuilder->build($indexName, $query, $facetsNames, $filter)->setLimit(1);
        }

        return $searchQueries;
    }
}
