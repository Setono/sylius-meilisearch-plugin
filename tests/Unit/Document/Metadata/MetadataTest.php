<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
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

        self::assertCount(2, $metadata->getMapProductAttributes());
        self::assertSame(
            [
                'collection' => ['t_shirt_collection', 'dress_collection'],
                'brand' => ['t_shirt_brand', 'dress_brand'],
            ],
            $metadata->getMapProductAttributes(),
        );

        self::assertCount(1, $metadata->getMapProductOptions());
        self::assertSame(
            ['size' => ['t_shirt_size', 'dress_size']],
            $metadata->getMapProductOptions(),
        );
    }
}
