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

        $result = $this->provideSearchResult($results, $searchRequest);

        return SearchResult::fromMeilisearchSearchResult($this->index, $result);
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function provideSearchResult(array $results, SearchRequest $searchRequest): MeilisearchSearchResult
    {
        /** @var array{facetDistribution?: array<string, array<string, int>>, facetStats?: array<string, mixed>} $firstResult */
        $firstResult = current($results);

        /** @var list<array<string, array<string, int>>> $facetDistributions */
        $facetDistributions = array_column($results, 'facetDistribution');
        $facetDistribution = [] === $facetDistributions ? [] : array_merge(...$facetDistributions);

        // Each facet's disjunctive sub-query only excludes its own filter, so an over-restrictive
        // filter on another facet (e.g. an impossible price range) can empty a facet's stats in the
        // main query. Merge the stats from every sub-query too — not just the main query's — so a
        // range facet whose sub-query still matched documents keeps rendering on an empty result page.
        /** @var list<array<string, mixed>> $facetStatsList */
        $facetStatsList = array_column($results, 'facetStats');
        $facetStats = [] === $facetStatsList ? [] : array_merge(...$facetStatsList);

        // Keep every currently-selected value of a choice facet present, with a zero count when the
        // current result set no longer contains it, so a shopper who over-filters into an empty
        // result set can still see — and uncheck — their selection instead of losing the filter UI.
        foreach ($this->index->metadata()->facetableAttributes as $name => $facet) {
            if ('array' !== $facet->type || !isset($searchRequest->filters[$name])) {
                continue;
            }

            /** @var mixed $selected */
            foreach ((array) $searchRequest->filters[$name] as $selected) {
                if (is_string($selected) && '' !== $selected) {
                    $facetDistribution[$name][$selected] ??= 0;
                }
            }
        }

        $firstResult['facetDistribution'] = $facetDistribution;
        $firstResult['facetStats'] = $facetStats;

        return new MeilisearchSearchResult($firstResult);
    }
}
