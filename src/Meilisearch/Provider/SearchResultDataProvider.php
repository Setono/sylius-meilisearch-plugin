<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Provider;

use Meilisearch\Search\SearchResult;

final class SearchResultDataProvider
{
    public static function getPageStartHit(SearchResult $searchResult): int
    {
        return (int) $searchResult->getPage() * (int) $searchResult->getHitsPerPage() - (int) $searchResult->getHitsPerPage();
    }

    public static function getPageEndHit(SearchResult $searchResult): int
    {
        $totalHits = (int) $searchResult->getTotalHits();
        $page = (int) $searchResult->getPage();
        $hitsPerPage = (int) $searchResult->getHitsPerPage();

        return ($totalHits < $page * $hitsPerPage) ? $totalHits : $page * $hitsPerPage;
    }
}
