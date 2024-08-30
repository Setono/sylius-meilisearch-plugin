<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;

interface SearchQueryBuilderInterface
{
    /**
     * @param array<string> $facets
     * @param array<string, mixed> $filter
     */
    public function build(string $indexName, string $query, array $facets, array $filter): SearchQuery;
}
