<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Builder;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\CompositeFilterBuilder;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FilterBuilderInterface;

final class CompositeFilterBuilderTest extends TestCase
{
    public function test_it_returns_filters(): void
    {
        $brandFilterBuilder = $this->createMock(FilterBuilderInterface::class);
        $brandFilterBuilder->method('build')->willReturn(['(brand = "brand1")']);

        $sizeFilterBuilder = $this->createMock(FilterBuilderInterface::class);
        $sizeFilterBuilder->method('build')->willReturn(['(size = "size1" OR size = "size2")']);

        $compositeFilterBuilder = new CompositeFilterBuilder([$brandFilterBuilder, $sizeFilterBuilder]);

        $onSaleFacet = new Facet('onSale', 'bool');
        $brandFacet = new Facet('brand', 'string');
        $sizeFacet = new Facet('size', 'string');

        $filters = $compositeFilterBuilder->build(
            ['onSale' => $onSaleFacet, 'brand' => $brandFacet, 'size' => $sizeFacet],
            ['onSale' => true, 'brand' => ['brand1'], 'size' => ['size1', 'size2']],
        );

        $this->assertSame([
            '(brand = "brand1")',
            '(size = "size1" OR size = "size2")',
        ], $filters);
    }
}
