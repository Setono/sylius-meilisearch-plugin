<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Meilisearch\Search\SearchResult;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;

final class SearchEngine implements SearchEngineInterface
{
    public function __construct(
        private readonly MetadataFactoryInterface $metadataFactory,
        private readonly FilterBuilderInterface $filterBuilder,
        private readonly Index $index,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly Client $client,
        private readonly int $hitsPerPage,
    ) {
    }

    public function execute(?string $query, array $parameters = []): SearchResult
    {
        $page = max(1, (int) ($parameters['p'] ?? 1));
        $sort = (string) ($parameters['sort'] ?? '');

        $metadata = $this->metadataFactory->getMetadataFor($this->index->document);
        $indexUid = $this->indexNameResolver->resolve($this->index);
        $query = $query ?? '';
        $facets = array_map(static fn (Facet $facet) => $facet->name, $metadata->getFacets());
        $filter = $this->filterBuilder->build($parameters);

        $mainQuery = $this->buildSearchQuery($indexUid, $query, $facets, $filter)
            ->setHitsPerPage($this->hitsPerPage)
            ->setPage($page)
        ;

        if ('' !== $sort) {
            $mainQuery->setSort([$sort]);
        }

        $results = $this->client->multiSearch([
            $mainQuery,
            ...$this->createSearchQueries($indexUid, $facets, $query),
        ])['results'] ?? [];
        /** @var array{facetDistribution: array<string, int>} $firstResult */
        $firstResult = current($results);
        $firstResult['facetDistribution'] = array_merge(...array_column($results, 'facetDistribution'));

        return new SearchResult($firstResult);
    }

    /**
     * @param array<string> $facets
     * @return array<SearchQuery>
     */
    private function createSearchQueries(string $indexUid, array $facets, ?string $query): array
    {
        $searchQueries = [];

        foreach ($facets as $facet) {
            $facets = [$facet];
            $filteredFacets = array_filter(
                $parameters['facets'] ?? [],
                static fn ($value) => $value !== $facet, ARRAY_FILTER_USE_KEY,
            );
            $filter = $this->filterBuilder->build($filteredFacets);

            $searchQueries[] = $this->buildSearchQuery($indexUid, $query, $facets, $filter)->setLimit(1);
        }

        return $searchQueries;
    }

    /**
     * @param array<string> $facets
     * @param array<string, mixed> $filter
     */
    private function buildSearchQuery(string $indexUid, ?string $query, array $facets, array $filter): SearchQuery
    {
        return (new SearchQuery())
            ->setIndexUid($indexUid)
            ->setQuery($query ?? '')
            ->setFacets($facets)
            ->setFilter($filter)
        ;
    }
}
