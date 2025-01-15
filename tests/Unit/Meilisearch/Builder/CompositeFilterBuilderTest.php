<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Builder;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\ArrayFilterBuilder;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\BooleanFilterBuilder;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\CompositeFilterBuilder;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\FloatFilterBuilder;

final class CompositeFilterBuilderTest extends TestCase
{
    use ProphecyTrait;

    public function test_it_returns_filters(): void
    {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $compositeFilterBuilder = new CompositeFilterBuilder($eventDispatcher->reveal());
        $compositeFilterBuilder->add(new ArrayFilterBuilder());
        $compositeFilterBuilder->add(new BooleanFilterBuilder());
        $compositeFilterBuilder->add(new FloatFilterBuilder());

        $onSaleFacet = new Facet('onSale', 'bool');
        $ecoFriendlyFacet = new Facet('ecoFriendly', 'bool');
        $brandFacet = new Facet('brand', 'array');
        $sizeFacet = new Facet('size', 'array');

        $filters = $compositeFilterBuilder->build(
            ['onSale' => $onSaleFacet, 'ecoFriendly' => $ecoFriendlyFacet, 'brand' => $brandFacet, 'size' => $sizeFacet],
            ['onSale' => true, 'ecoFriendly' => true, 'brand' => ['brand1'], 'size' => ['size1', 'size2']],
        );

        $this->assertSame([
            '(brand = "brand1")',
            '(size = "size1" OR size = "size2")',
            'onSale = true',
            'ecoFriendly = true',
        ], $filters);
    }
}
