<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;
use Webmozart\Assert\Assert;

final class SearchQueryBuilder implements SearchQueryBuilderInterface
{
    public function build(string $indexName, ?string $query, array $facets, array $filter): SearchQuery
    {
        $facets = array_values($facets);
        Assert::allStringNotEmpty($facets);
        Assert::allStringNotEmpty($filter);

        $searchQuery = new SearchQuery();

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
