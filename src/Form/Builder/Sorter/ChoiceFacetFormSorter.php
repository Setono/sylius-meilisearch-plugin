<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

final class ChoiceFacetFormSorter
{
    public static function sort(array $choices, array $template): array
    {
        return array_merge(array_flip(array_values(array_intersect($template, $choices))), $choices);
    }
}
