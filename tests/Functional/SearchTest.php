<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;

final class SearchTest extends FunctionalTestCase
{
    public function testItProvidesSearchResults(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans'));

        self::assertSame(8, $result->totalHits);
    }

    public function testItProvidesSearchResultByMultipleCriteria(): void
    {
        $brands = ['Celsius Small', 'You are breathtaking'];

        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);

        // Derive the price window from the unfiltered search results so the test
        // does not depend on the randomized fixture prices
        $unfiltered = $searchEngine->execute(new SearchRequest('jeans', ['brand' => $brands]));

        $prices = [];
        foreach ($unfiltered->hits as $unfilteredHit) {
            self::assertIsNumeric($unfilteredHit['price']);
            $prices[] = (float) $unfilteredHit['price'];
        }

        self::assertNotEmpty($prices);
        sort($prices);

        // A window spanning the cheapest hit to the median hit is guaranteed to match at least one product
        $min = (int) floor($prices[0]);
        $max = (int) ceil($prices[intdiv(count($prices) - 1, 2)]);

        $result = $searchEngine->execute(
            new SearchRequest('jeans', [
                'brand' => $brands,
                'price' => ['min' => $min, 'max' => $max],
            ]),
        );

        self::assertNotEmpty($result->hits);

        foreach ($result->hits as $hit) {
            self::assertIsNumeric($hit['price']);
            self::assertGreaterThanOrEqual($min, (float) $hit['price']);
            self::assertLessThanOrEqual($max, (float) $hit['price']);
            self::assertContains(((array) $hit['brand'])[0], $brands);
        }
    }

    public function testItAlwaysDisplaysFullFacetDistribution(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(
            new SearchRequest('jeans', [
                'brand' => ['Celsius Small'],
            ]),
        );

        self::assertSame(1, $result->totalHits);
        self::assertCount(4, $result->facetDistribution['brand']);
    }
}
