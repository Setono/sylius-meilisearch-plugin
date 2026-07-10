<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Document\Product;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Config\IndexRegistry
 */
final class IndexRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_with_the_requested_and_available_names_in_the_correct_order(): void
    {
        $registry = new IndexRegistry();
        $registry->add(new Index('products', Product::class, [], new Container()));
        $registry->add(new Index('taxons', Product::class, [], new Container()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No index exists with the name nope. Available indexes are: [products, taxons]');

        $registry->get('nope');
    }

    /**
     * @test
     */
    public function it_returns_a_registered_index(): void
    {
        $registry = new IndexRegistry();
        $index = new Index('products', Product::class, [], new Container());
        $registry->add($index);

        self::assertSame($index, $registry->get('products'));
    }
}
