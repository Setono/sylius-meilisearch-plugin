<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Client;
use Meilisearch\Search\SearchResult as MeilisearchSearchResult;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\MultiSearchBuilderInterface;

final class SearchEngine implements SearchEngineInterface
{
    public function __construct(
        private readonly Index $index,
        private readonly Client $client,
        private readonly MultiSearchBuilderInterface $multiSearchBuilder,
    ) {
    }

    public function execute(SearchRequest $searchRequest): SearchResult
    {
        $queries = $this->multiSearchBuilder->build($this->index, $searchRequest);

        /** @var array<MeilisearchSearchResult> $results */
        $results = $this->client->multiSearch($queries)['results'] ?? [];

        $result = $this->provideSearchResult($results);

        return SearchResult::fromMeilisearchSearchResult($this->index, $result);
    }

    private function provideSearchResult(array $results): MeilisearchSearchResult
    {
        /** @var array{facetDistribution: array<string, int>} $firstResult */
        $firstResult = current($results);

        /** @psalm-suppress MixedArgument (just for now) */
        $firstResult['facetDistribution'] = array_merge(...array_column($results, 'facetDistribution'));

        return new MeilisearchSearchResult($firstResult);
    }
}
