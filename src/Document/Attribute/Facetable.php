<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Facetable extends Filterable
{
    /**
     * @param string|null $sorter The facet-value sorter to apply: the service id of a service
     *   tagged "setono_sylius_meilisearch.filter_values_sorter" (autoconfiguration adds the tag to
     *   every Form\Builder\Sorter\FilterValuesSorterInterface implementation). The shipped
     *   SizeSorter is registered under its FQCN, so `sorter: SizeSorter::class` works out of the box.
     */
    public function __construct(public readonly int $position = 0, public readonly ?string $sorter = null)
    {
    }
}
