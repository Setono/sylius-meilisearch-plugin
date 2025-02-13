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
        public readonly string $query,
        /** @var array<int, array> $hits */
        public readonly array $hits,
        public readonly int $totalHits,
        public readonly int $page,
        public readonly int $pageSize,
        public readonly int $totalPages,
        public readonly FacetDistribution $facetDistribution,
        public readonly ?string $sort = null,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromMeilisearchSearchResult(Index $index, MeilisearchSearchResult $meilisearchSearchResult): self
    {
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
            $meilisearchSearchResult->getQuery(),
            $meilisearchSearchResult->getHits(),
            $totalHits,
            $page,
            $pageSize,
            $totalPages,
            new FacetDistribution($meilisearchSearchResult->getFacetDistribution(), $meilisearchSearchResult->getFacetStats()),
        );
    }
}
