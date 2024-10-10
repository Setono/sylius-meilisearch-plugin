<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;

interface FilterBuilderInterface
{
    /**
     * @param array<string, Facet> $facets
     * @param array<string, mixed> $facetsValues
     *
     * @return list<string>
     */
    public function build(array $facets, array $facetsValues): array;
}
