<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

final class BooleanFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets, array $facetsValues): array
    {
        $filters = [];

        foreach ($facets as $facet) {
            if ($facet->type === 'bool' && isset($facetsValues[$facet->name])) {
                $value = ($facetsValues[$facet->name] === '1') ? 'true' : 'false';
                $filters = [$facet->name . ' = ' . $value];
            }
        }

        return $filters;
    }
}
