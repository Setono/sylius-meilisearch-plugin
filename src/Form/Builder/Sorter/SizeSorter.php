<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

use DragonCode\SizeSorter\Sorter;

final class SizeSorter implements ChoiceValuesSorterInterface
{
    public function sort(array $choices): array
    {
        return Sorter::sort($choices)->toArray();
    }
}
