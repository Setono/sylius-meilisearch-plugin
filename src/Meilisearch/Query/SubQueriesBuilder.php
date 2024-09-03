<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FilterBuilderInterface;

final class SubQueriesBuilder implements SubQueriesBuilderInterface
{
    public function __construct(
        private readonly FilterBuilderInterface $filterBuilder,
        private readonly SearchQueryBuilderInterface $searchQueryBuilder,
    ) {
    }

    public function build(string $indexName, string $query, array $facets, array $filters): array
    {
        $searchQueries = [];

        foreach ($facets as $facet) {
            $facetsNames = [$facet->name];
            /** @var array<string, mixed> $filteredFacets */
            $filteredFacets = array_filter($filters, static fn ($value) => $value !== $facet->name, \ARRAY_FILTER_USE_KEY);
            /** @var array<string, mixed> $filter */
            $filter = $this->filterBuilder->build($facets, $filteredFacets);

            $searchQueries[] = $this->searchQueryBuilder->build($indexName, $query, $facetsNames, $filter)->setLimit(1);
        }

        return $searchQueries;
    }
}
