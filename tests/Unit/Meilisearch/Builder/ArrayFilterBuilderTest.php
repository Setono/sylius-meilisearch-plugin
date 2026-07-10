<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Builder;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\ArrayFilterBuilder;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\ArrayFilterBuilder
 */
final class ArrayFilterBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function it_builds_a_filter_expression_for_array_facets(): void
    {
        $builder = new ArrayFilterBuilder();

        $filters = $builder->build(
            ['brand' => new Facet('brand', 'array')],
            ['brand' => ['brand1', 'brand2']],
        );

        self::assertSame(['(brand = "brand1" OR brand = "brand2")'], $filters);
    }

    /**
     * @test
     */
    public function it_escapes_double_quotes_and_backslashes_in_values(): void
    {
        $builder = new ArrayFilterBuilder();

        $filters = $builder->build(
            ['brand' => new Facet('brand', 'array')],
            ['brand' => ['fo"o', 'ba\\r']],
        );

        // A double quote and a backslash must be escaped so the value cannot break out of the
        // quoted string or inject filter syntax. Meilisearch un-escapes these back to fo"o / ba\r.
        self::assertSame(['(brand = "fo\\"o" OR brand = "ba\\\\r")'], $filters);
    }

    /**
     * @test
     */
    public function it_skips_empty_and_non_string_values(): void
    {
        $builder = new ArrayFilterBuilder();

        $filters = $builder->build(
            ['brand' => new Facet('brand', 'array')],
            ['brand' => ['', 'valid', 123, null]],
        );

        self::assertSame(['(brand = "valid")'], $filters);
    }
}
