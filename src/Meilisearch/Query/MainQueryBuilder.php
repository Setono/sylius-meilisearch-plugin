<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;

final class MainQueryBuilder implements MainQueryBuilderInterface
{
    public function __construct(
        private readonly int $hitsPerPage,
        private readonly SearchQueryBuilder $searchQueryBuilder,
    ) {
    }

    /**
     * @param array<string> $facetsNames
     * @param array<string, mixed> $filters
     */
    public function build(
        string $indexName,
        string $query,
        array $facetsNames,
        array $filters,
        int $pageNumber,
        string $sort,
    ): SearchQuery {
        $mainQuery = $this->searchQueryBuilder
            ->build($indexName, $query, $facetsNames, $filters)
            ->setHitsPerPage($this->hitsPerPage)
            ->setPage($pageNumber)
        ;

        if ('' !== $sort) {
            $mainQuery->setSort([$sort]);
        }

        return $mainQuery;
    }
}
