<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Routing\SearchRouteLoader;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Routing\SearchRouteLoader
 */
final class SearchRouteLoaderTest extends TestCase
{
    /**
     * @test
     */
    public function it_registers_no_routes_when_search_is_disabled(): void
    {
        $loader = new SearchRouteLoader(enabled: false);

        $routes = $loader->load('.', 'setono_sylius_meilisearch_search');

        self::assertCount(0, $routes);
    }

    /**
     * @test
     */
    public function it_supports_only_its_own_type(): void
    {
        $loader = new SearchRouteLoader(enabled: true);

        self::assertTrue($loader->supports('.', 'setono_sylius_meilisearch_search'));
        self::assertFalse($loader->supports('.', 'yaml'));
        self::assertFalse($loader->supports('.', null));
    }
}
