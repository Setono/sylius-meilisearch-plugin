<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

// todo this should be refactored
final class FilterBuilder implements FilterBuilderInterface
{
    public function build(array $parameters): array
    {
        $filters = [];

        $query = (array) ($parameters['facets'] ?? $parameters);

        if (isset($query['onSale'])) {
            $filters[] = 'onSale = true';
        }

        if (isset($query['brand'])) {
            $brandQuery = [];
            /** @var string $brand */
            foreach ($query['brand'] as $brand) {
                $brandQuery[] = sprintf('brand = "%s"', $brand);
            }

            $filters[] = '(' . implode(' OR ', $brandQuery) . ')';
        }

        if (isset($query['size'])) {
            $sizeQuery = [];
            /** @var string $size */
            foreach ($query['size'] as $size) {
                $sizeQuery[] = sprintf('size = "%s"', $size);
            }

            $filters[] = '(' . implode(' OR ', $sizeQuery) . ')';
        }

        return $filters;
    }
}
