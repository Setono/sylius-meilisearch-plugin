<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Meilisearch\Search\SearchResult;

interface SearchEngineInterface
{
    public function execute(?string $query, array $parameters = []): SearchResult;
}
