<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable as SortableAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Sortable;

final class MetadataTest extends TestCase
{
    /**
     * @test
     */
    public function it_returns_document(): void
    {
        $doc = new Document();
        $metadata = new Metadata($doc);
        self::assertSame($doc::class, $metadata->document);
    }

    /**
     * @test
     */
    public function it_returns_the_valid_sortable_values_respecting_direction_restrictions(): void
    {
        $metadata = new Metadata(Document::class);
        $metadata->sortableAttributes['createdAt'] = new Sortable('createdAt');
        $metadata->sortableAttributes['price'] = new Sortable('price', SortableAttribute::ASC);

        self::assertSame([
            'createdAt:asc',
            'createdAt:desc',
            'price:asc',
        ], $metadata->getSortableValues());
    }
}
