<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FacetValuesSorterInterface;
use Webmozart\Assert\Assert;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Facetable extends Filterable
{
    /** @param class-string<FacetValuesSorterInterface>|null $sorter */
    public function __construct(public readonly ?string $sorter = null)
    {
        if ($this->sorter === null) {
            return;
        }

        Assert::true(is_a($sorter, FacetValuesSorterInterface::class, true));
    }
}
