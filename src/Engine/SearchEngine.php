<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Search\SearchResult as MeilisearchSearchResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Query\MultiSearchBuilderInterface;
use Webmozart\Assert\Assert;

final class SearchEngine implements SearchEngineInterface
{
    public function __construct(
        private readonly Index $index,
        private readonly Client $client,
        private readonly MultiSearchBuilderInterface $multiSearchBuilder,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function execute(SearchRequest $searchRequest): SearchResult
    {
        $queries = $this->multiSearchBuilder->build($this->index, $searchRequest);

        try {
            $response = $this->client->multiSearch($queries);
        } catch (ApiException $e) {
            if ('invalid_search_facets' !== $e->errorCode) {
                throw $e;
            }

            // A facet in the metadata is not (yet) filterable in Meilisearch. This happens right after
            // an attribute was made facetable (in code or in the admin) but before the settings update
            // task was processed by Meilisearch. Retry without facets so the search page degrades to a
            // facet-less result instead of erroring
            $this->logger->warning(sprintf(
                'Meilisearch rejected the requested facets for index "%s" (%s). This usually means the index settings are not up to date yet - the search was retried without facets',
                $this->index->name,
                $e->getMessage(),
            ));

            foreach ($queries as $query) {
                $query->setFacets([]);
            }

            $response = $this->client->multiSearch($queries);
        }

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
