<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

final class FloatFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets, array $facetsValues): array
    {
        $filters = [];

        foreach ($facets as $facet) {
            if ($facet->type === 'float' && isset($facetsValues[$facet->name])) {
                /** @var array<string, float> $values */
                $values = $facetsValues[$facet->name];

                if (isset($values['min'])) {
                    $filters[] = $facet->name . '>=' . $values['min'];
                }
                if (isset($values['max'])) {
                    $filters[] = $facet->name . '<=' . $values['max'];
                }
            }
        }

        return $filters;
    }
}
