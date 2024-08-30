<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;

interface FilterBuilderInterface
{
    /**
     * @param array<string, Facet> $facets
     * @param array<string, mixed> $facetsValues
     *
     * @return array<string>
     */
    public function build(array $facets, array $facetsValues): array;

    public function supports(array $facets): bool;
}
