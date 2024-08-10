<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Document as BaseDocument;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;

final class MetadataTest extends TestCase
{
    /**
     * @test
     */
    public function it_resolves_attributes(): void
    {
        $metadata = new Metadata(Document::class);

        self::assertCount(2, $metadata->getFacets());
    }
}

final class Document extends BaseDocument
{
    #[Facet]
    public ?string $name = null;

    #[Facet]
    public ?int $price = null;
}
