<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\ChoiceValuesSorterInterface;
use Webmozart\Assert\Assert;

// todo should this be renamed to Facetable to be consistent the other -able classes? Or does it just sound stupid?
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Facet extends Filterable
{
    public function __construct(public ?string $sorter = null)
    {
        if ($this->sorter === null) {
            return;
        }

        Assert::true(is_a($sorter, ChoiceValuesSorterInterface::class, true));
    }
}
