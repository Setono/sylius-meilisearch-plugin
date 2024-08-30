<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

use Setono\CompositeCompilerPass\CompositeService;

/** @extends CompositeService<FilterBuilderInterface> */
final class CompositeFilterBuilder extends CompositeService implements FilterBuilderInterface
{
    public function build(array $facets, array $facetsValues): array
    {
        $filters = [];

        foreach ($this->services as $filterBuilder) {
            $filters = array_merge($filters, $filterBuilder->build($facets, $facetsValues));
        }

        return $filters;
    }
}
