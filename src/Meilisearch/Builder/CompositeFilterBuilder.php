<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

final class CompositeFilterBuilder implements FilterBuilderInterface
{
    /**
     * @param iterable<FilterBuilderInterface> $filterBuilders
     */
    public function __construct(
        private readonly iterable $filterBuilders,
    ) {
    }

    public function build(array $facets, array $facetsValues): array
    {
        $filters = [];

        foreach ($this->filterBuilders as $filterBuilder) {
            if ($filterBuilder->supports($facets)) {
                $filters = array_merge($filters, $filterBuilder->build($facets, $facetsValues));
            }
        }

        return $filters;
    }

    public function supports(array $facets): bool
    {
        return true;
    }
}
