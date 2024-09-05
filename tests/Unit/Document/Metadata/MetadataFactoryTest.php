<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactory;

final class MetadataFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testItCreatesMetadataWithDocument(): void
    {
        $factory = new MetadataFactory();

        self::assertEquals(new Metadata(Document::class), $factory->getMetadataFor(Document::class));
    }
}
