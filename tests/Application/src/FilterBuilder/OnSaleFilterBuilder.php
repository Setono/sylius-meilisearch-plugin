<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\FilterBuilder;

use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;

final class OnSaleFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets): string|array
    {
        return 'onSale = true';
    }

    public function supports(array $facets): bool
    {
        return isset($facets['onSale']);
    }
}
