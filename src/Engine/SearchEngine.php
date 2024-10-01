<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Meilisearch\Search\SearchResult;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FilterBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\MainQueryBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\SubQueriesBuilderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexName\IndexNameResolverInterface;

final class SearchEngine implements SearchEngineInterface
{
    public function __construct(
        private readonly MetadataFactoryInterface $metadataFactory,
        private readonly FilterBuilderInterface $filterBuilder,
        private readonly Index $index,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly Client $client,
        private readonly MainQueryBuilderInterface $mainQueryBuilder,
        private readonly SubQueriesBuilderInterface $subQueriesBuilder,
    ) {
    }

    public function execute(SearchRequest $searchRequest): SearchResult
    {
        $indexName = $this->indexNameResolver->resolve($this->index);
        $metadata = $this->metadataFactory->getMetadataFor($this->index->document);
        $facetsNames = $metadata->getFacetableAttributeNames();
        $facets = $metadata->getFacetableAttributes();

        /** @var array<string, mixed> $filters */
        $filters = $this->filterBuilder->build($facets, $searchRequest->filters);

        $mainQuery = $this->mainQueryBuilder->build(
            $indexName,
            $searchRequest->query ?? '',
            $facetsNames,
            $filters,
            $searchRequest->page,
            $searchRequest->sort ?? '',
        );

        /** @var list<SearchQuery> $queries */
        $queries = array_merge(
            [$mainQuery],
            $this->subQueriesBuilder->build($indexName, $searchRequest->query ?? '', $facets, $searchRequest->filters),
        );

        /** @var array<SearchResult> $results */
        $results = $this->client->multiSearch($queries)['results'] ?? [];

        return $this->provideSearchResult($results);
    }

    private function provideSearchResult(array $results): SearchResult
    {
        /** @var array{facetDistribution: array<string, int>} $firstResult */
        $firstResult = current($results);
        /** @psalm-suppress MixedArgument (just for now) */
        $firstResult['facetDistribution'] = array_merge(...array_column($results, 'facetDistribution'));

        return new SearchResult($firstResult);
    }
}
