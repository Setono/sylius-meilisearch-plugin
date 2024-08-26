<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

final class Searchable
{
    public function __construct(
        public readonly string $name,
        /**
         * The higher the priority, the higher the attribute will be prioritized in the search
         */
        public readonly int $priority = 0,
    ) {
    }
}
