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
        /** @var array<string, mixed> $facetsFilter */
        $facetsFilter = (array) ($parameters['facets'] ?? []);

        $metadata = $this->metadataFactory->getMetadataFor($this->index->document);
        $indexUid = $this->indexNameResolver->resolve($this->index);
        $query = $query ?? '';
        /** @var array<string> $facetsNames */
        $facetsNames = $metadata->getFacetableAttributeNames();
        /** @var array<string, mixed> $filter */
        $filter = $this->filterBuilder->build($metadata->getFacetableAttributes(), $facetsFilter);

        $mainQuery = $this->buildSearchQuery($indexUid, $query, $facetsNames, $filter)
            ->setHitsPerPage($this->hitsPerPage)
            ->setPage($page)
        ;

        if ('' !== $sort) {
            $mainQuery->setSort([$sort]);
        }

        /** @var list<SearchQuery> $queries */
        $queries = array_merge([$mainQuery], $this->createSearchQueries($indexUid, $metadata->getFacetableAttributes(), $facetsNames, $parameters, $query));
        /** @var array<SearchResult> $results */
        $results = $this->client->multiSearch($queries)['results'] ?? [];
        /** @var array{facetDistribution: array<string, int>} $firstResult */
        $firstResult = current($results);
        /** @psalm-suppress MixedArgument (just for now) */
        $firstResult['facetDistribution'] = array_merge(...array_column($results, 'facetDistribution'));

        return new SearchResult($firstResult);
    }

    /**
     * @param array<string, Facet> $facets
     * @param array<string> $facetsNames
     *
     * @return array<SearchQuery>
     */
    private function createSearchQueries(
        string $indexUid,
        array $facets,
        array $facetsNames,
        array $parameters,
        ?string $query,
    ): array {
        $searchQueries = [];

        foreach ($facetsNames as $facet) {
            $facetsNames = [$facet];
            /** @var array<string, mixed> $filteredFacets */
            $filteredFacets = array_filter(
                isset($parameters['facets']) ? (array) $parameters['facets'] : [],
                static fn ($value) => $value !== $facet,
                \ARRAY_FILTER_USE_KEY,
            );
            /** @var array<string, mixed> $filter */
            $filter = $this->filterBuilder->build($facets, $filteredFacets);

            $searchQueries[] = $this->buildSearchQuery($indexUid, $query, $facetsNames, $filter)->setLimit(1);
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
