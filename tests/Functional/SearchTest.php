<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;

/** @group functional */
final class SearchTest extends FunctionalTestCase
{
    public function testItProvidesSearchResults(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans');

        self::assertSame(8, $result->getHitsCount());
    }

    public function testItProvidesSearchResultByMultipleCriteria(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(
            'jeans',
            [
                'facets' => [
                    'brand' => ['Celsius small', 'You are breathtaking'],
                    'price' => ['min' => '30', 'max' => '45'],
                ],
            ],
        );

        /** @var array $hit */
        $hit = $result->getHit(0);

        self::assertLessThan(45, (int) $hit['price']);
        self::assertGreaterThan(30, (int) $hit['price']);
        self::assertTrue(in_array(((array) $hit['brand'])[0], ['Celsius small', 'You are breathtaking'], true));
    }

    public function testItAlwaysDisplaysFullFacetDistribution(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(
            'jeans',
            ['facets' => ['brand' => ['Celsius small']]],
        );

        $this->assertSame(1, $result->getHitsCount());
        $this->assertCount(4, (array) $result->getFacetDistribution()['brand']);
    }
}
