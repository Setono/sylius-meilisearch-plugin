<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event\Search;

use Meilisearch\Search\SearchResult;

final class SearchResultReceived
{
    public function __construct(public readonly SearchResult $searchResult)
    {
    }
}
