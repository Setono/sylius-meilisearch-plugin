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
}
