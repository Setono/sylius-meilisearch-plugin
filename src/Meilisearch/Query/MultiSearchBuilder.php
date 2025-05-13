<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FilterBuilderInterface;

final class MultiSearchBuilder implements MultiSearchBuilderInterface
{
    public function __construct(
        private readonly SearchQueryBuilderInterface $searchQueryBuilder,
        private readonly FilterBuilderInterface $filterBuilder,
        /** @var positive-int $hitsPerPage */
        private readonly int $hitsPerPage,
    ) {
    }

    public function build(Index $index, SearchRequest $searchRequest): array
    {
        $metadata = $index->metadata();
        $facetsNames = $metadata->getFacetableAttributeNames();
        $facets = $metadata->facetableAttributes;

        $filters = $this->filterBuilder->build($facets, $searchRequest->filters);

        return array_merge(
            [$this->buildSearchQuery($index, $searchRequest, $facetsNames, $filters)],
            $this->buildFacetQueries($index, $searchRequest, $facets, $searchRequest->filters),
        );
    }

    /**
     * @param list<string> $facetNames
     * @param list<string> $filters
     */
    private function buildSearchQuery(Index $index, SearchRequest $searchRequest, array $facetNames, array $filters): SearchQuery
    {
        $query = $this->searchQueryBuilder
            ->build($index->uid(), $searchRequest->query, $facetNames, $filters)
            ->setHitsPerPage($this->hitsPerPage)
            ->setPage($searchRequest->page)
        ;

        if (null !== $searchRequest->sort) {
            $query->setSort([$searchRequest->sort]);
        }

        return $query;
    }

    /**
     * @param array<string, Facet> $facets
     * @param array<string, mixed> $filters
     *
     * @return list<SearchQuery>
     */
    private function buildFacetQueries(Index $index, SearchRequest $searchRequest, array $facets, array $filters): array
    {
        $searchQueries = [];

        foreach ($facets as $facet) {
            /** @var array<string, mixed> $filteredFacets */
            $filteredFacets = array_filter($filters, static fn ($value) => $value !== $facet->name, \ARRAY_FILTER_USE_KEY);

            $searchQueries[] = $this->searchQueryBuilder->build(
                indexName: $index->uid(),
                query: $searchRequest->query,
                facets: [$facet->name],
                filter: $this->filterBuilder->build($facets, $filteredFacets),
            )
                ->setLimit(1)
            ;
        }

        return $searchQueries;
    }
}
