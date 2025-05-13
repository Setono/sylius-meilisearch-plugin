<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;

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
}
