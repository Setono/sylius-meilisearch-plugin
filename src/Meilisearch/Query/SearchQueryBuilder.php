<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;

final class SearchQueryBuilder implements SearchQueryBuilderInterface
{
    public function build(string $indexName, string $query, array $facets, array $filter): SearchQuery
    {
        return (new SearchQuery())
            ->setIndexUid($indexName)
            ->setQuery($query)
            ->setFacets($facets)
            ->setFilter($filter)
        ;
    }
}
