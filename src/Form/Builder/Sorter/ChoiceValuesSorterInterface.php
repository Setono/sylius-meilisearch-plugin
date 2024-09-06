<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

interface ChoiceValuesSorterInterface
{
    public function sort(array $choices): array;
}
