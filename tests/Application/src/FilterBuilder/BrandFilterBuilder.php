<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\FilterBuilder;

use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;

final class BrandFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets): string|array
    {
        $brandQuery = [];
        /** @var string $size */
        foreach ($facets['brand'] as $size) {
            $brandQuery[] = sprintf('brand = "%s"', $size);
        }

        return '(' . implode(' OR ', $brandQuery) . ')';
    }

    public function supports(array $facets): bool
    {
        return isset($facets['brand']);
    }
}
