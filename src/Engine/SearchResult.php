<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Search\SearchResult as MeilisearchSearchResult;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Webmozart\Assert\Assert;

final class SearchResult
{
    public function __construct(
        /** The index that was queried */
        public readonly Index $index,

        /** @var array<int, array> $hits */
        public readonly array $hits,
        public readonly int $totalHits,
        public readonly int $page,
        public readonly int $pageSize,
        public readonly int $totalPages,
        public readonly FacetDistribution $facetDistribution,

        /**
         * The request this result was produced from. Notice this is the request as it was executed,
         * which is not necessarily the one parsed from the query string: listeners of the
         * SearchRequestCreated event are allowed to change it before the search runs.
         *
         * It is null when the result was not created by the search engine, and last in the
         * parameter list because it was added after the other parameters
         */
        public readonly ?SearchRequest $request = null,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromMeilisearchSearchResult(
        Index $index,
        MeilisearchSearchResult $meilisearchSearchResult,
        ?SearchRequest $searchRequest = null,
    ): self {
        // TODO support estimated total number of search results. See https://www.meilisearch.com/docs/reference/api/search#exhaustive-and-estimated-total-number-of-search-results
        $page = $meilisearchSearchResult->getPage();
        Assert::notNull($page);

        $totalPages = $meilisearchSearchResult->getTotalPages();
        Assert::notNull($totalPages);

        $totalHits = $meilisearchSearchResult->getTotalHits();
        Assert::notNull($totalHits);

        $pageSize = $meilisearchSearchResult->getHitsPerPage();
        Assert::notNull($pageSize);

        return new self(
            $index,
            $meilisearchSearchResult->getHits(),
            $totalHits,
            $page,
            $pageSize,
            $totalPages,
            new FacetDistribution($meilisearchSearchResult->getFacetDistribution(), $meilisearchSearchResult->getFacetStats()),
            $searchRequest,
        );
    }
}
