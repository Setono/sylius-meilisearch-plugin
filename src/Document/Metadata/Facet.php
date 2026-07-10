<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

final class Facet
{
    /** @param string|null $sorter A FilterValuesSorterInterface service id, or (BC) a FQCN */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly int $position = 0,
        public readonly ?string $sorter = null,
    ) {
    }
}
