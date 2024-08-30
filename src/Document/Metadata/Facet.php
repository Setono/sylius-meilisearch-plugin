<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

final class Facet
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {
    }
}
