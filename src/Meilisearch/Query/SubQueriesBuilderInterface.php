<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;

interface SubQueriesBuilderInterface
{
    /**
     * @param array<string, Facet> $facets
     * @param array<string> $facetsNames
     *
     * @return array<SearchQuery>
     */
    public function build(
        string $indexName,
        string $query,
        array $facets,
        array $facetsNames,
        array $filters,
    ): array;
}
