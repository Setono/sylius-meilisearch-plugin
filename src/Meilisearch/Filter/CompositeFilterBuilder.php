<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

final class CompositeFilterBuilder implements FilterBuilderInterface
{
    /** @param iterable<FilterBuilderInterface> $filterBuilders */
    public function __construct(private readonly iterable $filterBuilders)
    {
    }

    public function build(array $facets, array $facetsValues): array
    {
        $filters = [];

        foreach ($this->filterBuilders as $filterBuilder) {
            $filters = array_merge($filters, $filterBuilder->build($facets, $facetsValues));
        }

        return $filters;
    }
}
