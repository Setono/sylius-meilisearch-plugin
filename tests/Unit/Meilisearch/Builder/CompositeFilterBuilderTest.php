<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Builder;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\CompositeFilterBuilder;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Builder\FilterBuilderInterface;

final class CompositeFilterBuilderTest extends TestCase
{
    public function test_it_returns_filters(): void
    {
        $brandFilterBuilder = $this->createMock(FilterBuilderInterface::class);
        $brandFilterBuilder->method('build')->willReturn(['(brand = "brand1")']);
        $brandFilterBuilder->method('supports')->willReturn(true);

        $sizeFilterBuilder = $this->createMock(FilterBuilderInterface::class);
        $sizeFilterBuilder->method('build')->willReturn(['(size = "size1" OR size = "size2")']);
        $sizeFilterBuilder->method('supports')->willReturn(true);

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

    public function test_it_uses_only_supported_filter_builders(): void
    {
        $brandFilterBuilder = $this->createMock(FilterBuilderInterface::class);
        $brandFilterBuilder->expects($this->never())->method('build');
        $brandFilterBuilder->method('supports')->willReturn(false);

        $sizeFilterBuilder = $this->createMock(FilterBuilderInterface::class);
        $sizeFilterBuilder->method('build')->willReturn(['(size = "size1" OR size = "size2")']);
        $sizeFilterBuilder->method('supports')->willReturn(true);

        $compositeFilterBuilder = new CompositeFilterBuilder([$brandFilterBuilder, $sizeFilterBuilder]);

        $onSaleFacet = new Facet('onSale', 'bool');
        $sizeFacet = new Facet('size', 'string');

        $filters = $compositeFilterBuilder->build(
            ['onSale' => $onSaleFacet, 'size' => $sizeFacet],
            ['onSale' => true, 'size' => ['size1', 'size2']],
        );

        $this->assertSame([
            '(size = "size1" OR size = "size2")',
        ], $filters);
    }
}
