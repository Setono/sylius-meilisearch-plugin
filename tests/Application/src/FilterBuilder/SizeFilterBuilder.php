<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\FilterBuilder;

use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;

final class SizeFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets): string|array
    {
        $sizeQuery = [];
        /** @var string $size */
        foreach ($facets['size'] as $size) {
            $sizeQuery[] = sprintf('size = "%s"', $size);
        }

        return '(' . implode(' OR ', $sizeQuery) . ')';
    }

    public function supports(array $facets): bool
    {
        return isset($facets['size']);
    }
}
