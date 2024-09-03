<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;

interface MainQueryBuilderInterface
{
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
    ): SearchQuery;
}
