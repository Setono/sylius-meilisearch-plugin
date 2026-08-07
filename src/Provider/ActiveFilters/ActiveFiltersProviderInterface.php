<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\ActiveFilters;

use Setono\SyliusMeilisearchPlugin\Engine\SearchResult;
use Symfony\Component\HttpFoundation\Request;

interface ActiveFiltersProviderInterface
{
    /**
     * Provides the filters that are active in the given request, each with the url that removes it.
     * The urls are built from the request because they point back at the page they are rendered on,
     * keeping the path and any parameter that has nothing to do with the search.
     *
     * The search result decides which of those filters are actually active: only the ones the search
     * was executed with, and, for a range filter, only when it narrows the facet's own bounds instead
     * of just mirroring them
     */
    public function provide(Request $request, ?SearchResult $searchResult = null): ActiveFilterCollection;
}
