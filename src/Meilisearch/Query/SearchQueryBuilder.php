<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;

final class SearchQueryBuilder implements SearchQueryBuilderInterface
{
    public function build(string $indexName, ?string $query, array $facets, array $filter): SearchQuery
    {
        $searchQuery = new SearchQuery();

        /** @psalm-suppress ArgumentTypeCoercion */
        $searchQuery
            ->setIndexUid($indexName)
            ->setFacets($facets)
            ->setFilter($filter)
        ;

        if (null !== $query) {
            $searchQuery->setQuery($query);
        }

        return $searchQuery;
    }
}
