<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Setono\SyliusMeilisearchPlugin\Engine\SearchEngine;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** @group functional */
final class SearchTest extends WebTestCase
{
    private static KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        self::$client = static::createClient(['environment' => 'test', 'debug' => true]);
    }

    public function testItProvidesSearchResults(): void
    {
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans');

        self::assertSame(8, $result->getHitsCount());
    }

    public function testItSortsSearchResultsByLowestPrice(): void
    {
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans', ['sort' => 'price:asc']);

        self::assertSame(8, $result->getHitsCount());

        $previousKey = null;
        foreach ($result->getHits() as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;
                continue;
            }

            self::assertGreaterThanOrEqual($result->getHit($previousKey)['price'], $hit['price']);
            $previousKey = $key;
        }
    }

    public function testItSortsSearchResultsByNewestDate(): void
    {
        $searchEngine = self::getContainer()->get(SearchEngine::class);
        $result = $searchEngine->execute('jeans', ['sort' => 'createdAt:desc']);

        self::assertSame(8, $result->getHitsCount());

        $previousKey = null;
        foreach ($result->getHits() as $key => $hit) {
            if ($previousKey === null) {
                $previousKey = $key;
                continue;
            }

            self::assertLessThanOrEqual($result->getHit($previousKey)['createdAt'], $hit['createdAt']);
            $previousKey = $key;
        }
    }
}
