<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\FilterValuesSorterInterface;
use Webmozart\Assert\Assert;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Facetable extends Filterable
{
    /**
     * TODO: Should be a service id
     *
     * @param class-string<FilterValuesSorterInterface>|null $sorter
     */
    public function __construct(public readonly int $position = 0, public readonly ?string $sorter = null)
    {
        if ($this->sorter === null) {
            return;
        }

        Assert::true(is_a($sorter, FilterValuesSorterInterface::class, true));
    }
}
