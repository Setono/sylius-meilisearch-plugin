<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Searchable
{
    public function __construct(
        /**
         * The higher the priority, the higher the attribute will be prioritized in the search
         */
        public readonly int $priority = 0,
    ) {
    }
}
