<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

use DragonCode\SizeSorter\Sorter;

final class SizeSorter implements FilterValuesSorterInterface
{
    public function sort(array $values): array
    {
        return Sorter::sort($values)->toArray();
    }
}
