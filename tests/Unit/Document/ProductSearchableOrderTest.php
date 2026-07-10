<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactory;
use Setono\SyliusMeilisearchPlugin\Document\Product;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Document\Product
 */
final class ProductSearchableOrderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * The built-in Product document ships ordered searchable defaults: name ranks above taxons.
     *
     * @test
     */
    public function it_ships_ordered_searchable_defaults_with_name_first(): void
    {
        $factory = new MetadataFactory($this->prophesize(EventDispatcherInterface::class)->reveal());

        $metadata = $factory->getMetadataFor(Product::class);

        self::assertSame(['name', 'taxons'], $metadata->getSearchableAttributeNames());
    }
}
