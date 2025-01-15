<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event\Search;

final class SearchFiltersBuilt
{
    public function __construct(
        /** @var list<string> $filters */
        public array $filters,
    ) {
    }
}
