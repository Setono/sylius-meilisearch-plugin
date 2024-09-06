<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

final class SizeSorter implements ChoiceValuesSorterInterface
{
    public function sort(array $choices): array
    {
        return array_merge(array_flip(array_values(array_intersect(['S', 'M', 'L', 'XL', 'XXL'], $choices))), $choices);
    }
}
