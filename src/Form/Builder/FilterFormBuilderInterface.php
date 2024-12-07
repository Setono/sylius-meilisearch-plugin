<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Symfony\Component\Form\FormBuilderInterface;

interface FilterFormBuilderInterface
{
    /**
     * @param array<string, int> $values The values of the facet. This could be ['red' => 10, 'blue' => 5] where the key is the facet value and the value is the number of matching documents
     * @param array{min: int|float, max: int|float}|null $stats The stats of the facet. This could be ['min' => 10, 'max' => 100] where min is the minimum value and max is the maximum value
     */
    public function build(FormBuilderInterface $builder, Facet $facet, array $values, array $stats = null): void;

    /**
     * @param array<string, int> $values
     * @param array{min: int|float, max: int|float}|null $stats
     */
    public function supports(Facet $facet, array $values, array $stats = null): bool;
}
