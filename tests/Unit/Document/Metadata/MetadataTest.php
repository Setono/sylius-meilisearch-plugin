<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MappedProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;

final class MetadataTest extends TestCase
{
    public function testItResolvesAttributes(): void
    {
        $metadata = new Metadata(Document::class);

        self::assertCount(5, $metadata->getFilterableAttributes());
        self::assertArrayHasKey('size', $metadata->getFilterableAttributes());
        self::assertArrayHasKey('price', $metadata->getFilterableAttributes());
        self::assertArrayHasKey('taxons', $metadata->getFilterableAttributes());

        self::assertCount(4, $metadata->getFacetableAttributes());
        self::assertArrayHasKey('size', $metadata->getFacetableAttributes());
        self::assertArrayHasKey('price', $metadata->getFacetableAttributes());

        self::assertCount(1, $metadata->getSearchableAttributes());
        self::assertArrayHasKey('name', $metadata->getSearchableAttributes());

        self::assertCount(1, $metadata->getSortableAttributes());
        self::assertArrayHasKey('price', $metadata->getSortableAttributes());

        self::assertCount(2, $metadata->getMappedProductAttributes());
        self::assertEquals([
            new MappedProductAttribute('collection', 'array', ['t_shirt_collection', 'dress_collection']),
            new MappedProductAttribute('brand', 'array', ['t_shirt_brand', 'dress_brand']),
        ], $metadata->getMappedProductAttributes());

        self::assertCount(1, $metadata->getMappedProductOptions());
        self::assertSame(
            ['size' => ['t_shirt_size', 'dress_size']],
            $metadata->getMappedProductOptions(),
        );
    }
}
