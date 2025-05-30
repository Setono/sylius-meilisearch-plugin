<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MappedProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactory;
use Setono\SyliusMeilisearchPlugin\Event\MetadataCreated;

final class MetadataFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_creates_metadata(): void
    {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(Argument::type(MetadataCreated::class))->shouldBeCalledOnce();

        $factory = new MetadataFactory($eventDispatcher->reveal());

        $metadata = $factory->getMetadataFor(Document::class);

        self::assertCount(5, $metadata->filterableAttributes);
        self::assertArrayHasKey('size', $metadata->filterableAttributes);
        self::assertArrayHasKey('price', $metadata->filterableAttributes);
        self::assertArrayHasKey('taxons', $metadata->filterableAttributes);

        self::assertCount(4, $metadata->facetableAttributes);
        self::assertArrayHasKey('size', $metadata->facetableAttributes);
        self::assertArrayHasKey('price', $metadata->facetableAttributes);

        self::assertCount(1, $metadata->searchableAttributes);
        self::assertArrayHasKey('name', $metadata->searchableAttributes);

        self::assertCount(1, $metadata->sortableAttributes);
        self::assertArrayHasKey('price', $metadata->sortableAttributes);

        self::assertCount(2, $metadata->mappedProductAttributes);
        self::assertEquals([
            new MappedProductAttribute('collection', 'array', ['t_shirt_collection', 'dress_collection']),
            new MappedProductAttribute('brand', 'array', ['t_shirt_brand', 'dress_brand']),
        ], $metadata->mappedProductAttributes);

        self::assertCount(1, $metadata->mappedProductOptions);
        self::assertSame(
            ['size' => ['t_shirt_size', 'dress_size']],
            $metadata->mappedProductOptions,
        );
    }
}
