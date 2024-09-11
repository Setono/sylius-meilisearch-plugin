<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FacetValuesSorterInterface;

final class Facet
{
    /** @param class-string<FacetValuesSorterInterface>|null $sorter */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $sorter = null,
    ) {
    }
}
