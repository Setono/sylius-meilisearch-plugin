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

            if ($facet->type === 'array' && isset($facetsValues[$facet->name])) {
                /** @var mixed $value */
                foreach ($facetsValues[$facet->name] as $value) {
                    if (!is_string($value) || '' === $value) {
                        continue;
                    }

                    $query[] = sprintf('%s = "%s"', $facet->name, $value);
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
