<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Setono\SyliusMeilisearchPlugin\Engine\SearchRequest;

final class SearchSortingTest extends FunctionalTestCase
{
    public function testItSortsSearchResultsByLowestPrice(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans', sort: 'price:asc'));

        self::assertSame(8, $result->totalHits);

        $previousKey = null;
        foreach ($result->hits as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;

                continue;
            }

            $previousHit = $result->hits[$previousKey];
            self::assertGreaterThanOrEqual($previousHit['price'], $hit['price']);
            $previousKey = $key;
        }
    }

    public function testItSortsSearchResultsByNewestDate(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans', sort: 'createdAt:desc'));

        self::assertSame(8, $result->totalHits);

        $previousKey = null;
        foreach ($result->hits as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;

                continue;
            }

            $previousHit = $result->hits[$previousKey];
            self::assertLessThanOrEqual($previousHit['createdAt'], $hit['createdAt']);
            $previousKey = $key;
        }
    }

    public function testItSortsResultsByBiggestDiscount(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute(new SearchRequest('jeans', sort: 'discount:desc'));

        $previousDiscount = null;
        /** @var array{discount: float} $hit */
        foreach ($result->hits as $hit) {
            if (null === $previousDiscount) {
                $previousDiscount = $hit['discount'];
            }

            self::assertLessThanOrEqual($previousDiscount, $hit['discount']);
        }
    }
}
