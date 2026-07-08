<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Client;
use Meilisearch\Search\SearchResult as MeilisearchSearchResult;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\MultiSearchBuilderInterface;
use Webmozart\Assert\Assert;

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

        $response = $this->client->multiSearch($queries);
        Assert::isArray($response);

        /** @var list<array<string, mixed>> $results */
        $results = $response['results'] ?? [];

        $result = $this->provideSearchResult($results);

        return SearchResult::fromMeilisearchSearchResult($this->index, $result);
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function provideSearchResult(array $results): MeilisearchSearchResult
    {
        /** @var array{facetDistribution?: array<string, array<string, int>>} $firstResult */
        $firstResult = current($results);

        /** @var list<array<string, array<string, int>>> $facetDistributions */
        $facetDistributions = array_column($results, 'facetDistribution');

        $firstResult['facetDistribution'] = array_merge(...$facetDistributions);

        return new MeilisearchSearchResult($firstResult);
    }
}
