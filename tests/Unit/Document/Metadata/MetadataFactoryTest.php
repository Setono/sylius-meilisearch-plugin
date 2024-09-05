<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactory;
use Symfony\Component\Cache\Adapter\NullAdapter;

final class MetadataFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testItCreatesMetadataWithDocument(): void
    {
        $factory = new MetadataFactory(new NullAdapter());

        self::assertEquals(new Metadata(Document::class), $factory->getMetadataFor(Document::class));
    }
}
