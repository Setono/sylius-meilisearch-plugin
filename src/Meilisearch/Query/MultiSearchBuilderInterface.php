<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Query;

use Meilisearch\Contracts\SearchQuery;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;

interface MultiSearchBuilderInterface
{
    /**
     * Will build a multi search query that handles the search and the facets
     *
     * todo explain somewhere why this is necessary
     *
     * @return list<SearchQuery>
     */
    public function build(Index $index, SearchRequest $searchRequest): array;
}
