<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters;

use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
use Symfony\Component\HttpFoundation\Request;

interface ActiveFiltersProviderInterface
{
    /**
     * Provides the filters that are active in the given request. The search result is used to decide
     * whether a range filter actually narrows the result set or just mirrors the facet's bounds
     */
    public function provide(Request $request, ?SearchResult $searchResult = null): ActiveFilterCollection;
}
