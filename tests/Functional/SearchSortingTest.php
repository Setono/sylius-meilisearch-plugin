<?php

declare(strict_types=1);

namespace Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** @group functional */
final class SearchSortingTest extends WebTestCase
{
    private static KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        self::$client = static::createClient(['environment' => 'test', 'debug' => true]);
    }

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