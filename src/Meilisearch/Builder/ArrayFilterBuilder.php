<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

final class ArrayFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets, array $facetsValues): array
    {
        $query = [];

        foreach ($facets as $facet) {
            if ($facet->type === 'array' && isset($facetsValues[$facet->name])) {
                foreach ($facetsValues[$facet->name] as $value) {
                    $query[] = sprintf('%s = "%s"', $facet->name, $value);
                }
            }
        }

        if (empty($query)) {
            return [];
        }

        return ['(' . implode(' OR ', $query) . ')'];
    }

    public function supports(array $facets): bool
    {
        return true;
    }
}
