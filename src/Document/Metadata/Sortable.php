<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

final class Sortable
{
    public function __construct(
        public readonly string $name,
        /**
         * The direction of the sorting. If null, both directions are allowed
         */
        public readonly ?string $direction = null,
    ) {
    }
}
