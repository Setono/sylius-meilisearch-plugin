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
            /** @var string $brand */
            foreach ($query['brand'] as $brand) {
                $filters[] = sprintf('brand = "%s"', $brand);
            }
        }

        return $filters;
    }
}
