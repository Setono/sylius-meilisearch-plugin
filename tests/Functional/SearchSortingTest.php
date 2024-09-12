<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;

/** @group functional */
final class SearchSortingTest extends FunctionalTestCase
{
    public function testItSortsSearchResultsByLowestPrice(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans', ['sort' => 'price:asc']);

        self::assertSame(8, $result->getHitsCount());

        $previousKey = null;
        foreach ($result->getHits() as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;

                continue;
            }

            $previousHit = (array) $result->getHit($previousKey);
            self::assertGreaterThanOrEqual($previousHit['price'], $hit['price']);
            $previousKey = $key;
        }
    }

    public function testItSortsSearchResultsByNewestDate(): void
    {
        /** @var SearchEngine $searchEngine */
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans', ['sort' => 'createdAt:desc']);

        self::assertSame(8, $result->getHitsCount());

        $previousKey = null;
        foreach ($result->getHits() as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;

                continue;
            }

            $previousHit = (array) $result->getHit($previousKey);
            self::assertLessThanOrEqual($previousHit['createdAt'], $hit['createdAt']);
            $previousKey = $key;
        }
    }
}
