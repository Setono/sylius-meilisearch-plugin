<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

interface FilterValuesSorterInterface
{
    /**
     * @param array<array-key, string> $values
     *
     * @return array<array-key, string>
     */
    public function sort(array $values): array;
}
