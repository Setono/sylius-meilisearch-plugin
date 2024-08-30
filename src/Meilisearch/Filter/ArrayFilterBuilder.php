<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

final class ArrayFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets, array $facetsValues): array
    {
        $query = [];

        foreach ($facets as $facet) {
            if ($facet->type === 'array' && isset($facetsValues[$facet->name])) {
                /** @var string $value */
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
}
