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

        // The sort parameter comes straight from the request. Validate it against the document's
        // sortable attributes before it reaches Meilisearch: an unknown attribute or an invalid
        // direction would otherwise make Meilisearch reject the whole query and error the page.
        // Anything that doesn't match silently falls back to relevance (no sort).
        $sort = null !== $searchRequest->sort && in_array($searchRequest->sort, $metadata->getSortableValues(), true)
            ? $searchRequest->sort
            : null;

        return array_merge(
            [$this->buildSearchQuery($index, $searchRequest, $facetsNames, $filters, $sort)],
            $this->buildFacetQueries($index, $searchRequest, $facets, $searchRequest->filters),
        );
    }

    /**
     * @param list<string> $facetNames
     * @param list<string> $filters
     */
    private function buildSearchQuery(Index $index, SearchRequest $searchRequest, array $facetNames, array $filters, ?string $sort): SearchQuery
    {
        $query = $this->searchQueryBuilder
            ->build($index->uid(), $searchRequest->query, $facetNames, $filters)
            ->setHitsPerPage($this->hitsPerPage)
            ->setPage($searchRequest->page)
        ;

        if (null !== $sort) {
            $query->setSort([$sort]);
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
            // A dedicated disjunctive sub-query is only needed for facets the user has actively
            // filtered on — only there must the facet's own filter be excluded to show its full
            // distribution. Every other facet reads its counts from the main query's
            // facetDistribution for free, so we skip its sub-query.
            if (!self::hasActiveSelection($facet, $filters)) {
                continue;
            }

            /** @var array<string, mixed> $filteredFacets */
            $filteredFacets = array_filter($filters, static fn ($key) => $key !== $facet->name, \ARRAY_FILTER_USE_KEY);

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

    /**
     * Whether the request carries a non-empty selection for the given facet.
     *
     * @param array<string, mixed> $filters
     */
    private static function hasActiveSelection(Facet $facet, array $filters): bool
    {
        if (!isset($filters[$facet->name])) {
            return false;
        }

        $value = $filters[$facet->name];

        if (is_array($value)) {
            $value = array_filter($value, static fn ($v): bool => $v !== '' && $v !== null && $v !== []);

            return [] !== $value;
        }

        // isset() above already guarantees a non-null value here
        return $value !== '';
    }
}
