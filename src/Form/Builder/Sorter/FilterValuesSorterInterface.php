<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

interface FilterValuesSorterInterface
{
    public function sort(array $values): array;
}
