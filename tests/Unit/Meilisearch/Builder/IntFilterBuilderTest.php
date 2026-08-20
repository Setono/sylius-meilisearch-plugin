<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Builder;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Facet;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\IntFilterBuilder;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\Filter\IntFilterBuilder
 */
final class IntFilterBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function it_builds_range_filter_expressions_for_int_facets(): void
    {
        $builder = new IntFilterBuilder();

        $filters = $builder->build(
            ['lumen' => new Facet('lumen', 'int')],
            ['lumen' => ['min' => '1000', 'max' => '3000']],
        );

        self::assertSame(['lumen>=1000', 'lumen<=3000'], $filters);
    }

    /**
     * @test
     */
    public function it_builds_a_single_bound(): void
    {
        $builder = new IntFilterBuilder();

        $filters = $builder->build(
            ['lumen' => new Facet('lumen', 'int')],
            ['lumen' => ['min' => '', 'max' => '3000']],
        );

        self::assertSame(['lumen<=3000'], $filters);
    }

    /**
     * @test
     */
    public function it_ignores_other_facet_types_and_non_numeric_values(): void
    {
        $builder = new IntFilterBuilder();

        $filters = $builder->build(
            [
                'price' => new Facet('price', 'float'),
                'lumen' => new Facet('lumen', 'int'),
            ],
            [
                'price' => ['min' => '10'],
                'lumen' => ['min' => 'abc', 'max' => '3000; DROP'],
            ],
        );

        self::assertSame([], $filters);
    }
}
