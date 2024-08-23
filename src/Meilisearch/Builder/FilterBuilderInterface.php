<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

interface FilterBuilderInterface
{
    public function build(array $facets): string|array;

    public function supports(array $facets): bool;
}
