<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

interface SearchEngineInterface
{
    public function execute(SearchRequest $searchRequest): SearchResult;
}
