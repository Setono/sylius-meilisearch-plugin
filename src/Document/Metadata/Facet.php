<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FilterValuesSorterInterface;

final class Facet
{
    /** @param class-string<FilterValuesSorterInterface>|null $sorter */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $sorter = null,
    ) {
    }
}
