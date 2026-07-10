<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

final class ArrayFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets, array $facetsValues): array
    {
        $queries = [];

        foreach ($facets as $facet) {
            $query = [];

            if ($facet->type === 'array' && isset($facetsValues[$facet->name]) && is_array($facetsValues[$facet->name])) {
                /** @var mixed $value */
                foreach ($facetsValues[$facet->name] as $value) {
                    if (!is_string($value) || '' === $value) {
                        continue;
                    }

                    // Escape backslashes and double quotes per Meilisearch's filter-expression
                    // rules so a value coming straight from the request cannot break out of the
                    // quoted string or inject filter syntax.
                    $query[] = sprintf('%s = "%s"', $facet->name, addcslashes($value, '\\"'));
                }
            }

            if ([] === $query) {
                continue;
            }

            $queries[] = '(' . implode(' OR ', $query) . ')';
        }

        return $queries;
    }
}
