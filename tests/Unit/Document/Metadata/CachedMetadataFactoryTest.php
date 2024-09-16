<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\CachedMetadataFactory;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;

final class CachedMetadataFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testItCreatesNewMetadataAndSavesItInCache(): void
    {
        $cacheItemPool = $this->prophesize(CacheItemPoolInterface::class);
        $baseFactory = $this->prophesize(MetadataFactoryInterface::class);
        $factory = new CachedMetadataFactory($baseFactory->reveal(), $cacheItemPool->reveal());

        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItemPool
            ->getItem('Setono.SyliusMeilisearchPlugin.Tests.Unit.Document.Metadata.Document')
            ->willReturn($cacheItem)
        ;
        $cacheItem->isHit()->willReturn(false);
        $metadata = new Metadata(Document::class);
        $baseFactory->getMetadataFor(Document::class)->willReturn($metadata);
        $cacheItem->set($metadata)->shouldBeCalled()->willReturn($cacheItem);
        $cacheItemPool->save($cacheItem->reveal())->shouldBeCalled();

        self::assertEquals(
            new Metadata(Document::class),
            $factory->getMetadataFor(Document::class),
        );
    }

    public function testItFetchesMetadataFromCache(): void
    {
        $cacheItemPool = $this->prophesize(CacheItemPoolInterface::class);
        $baseFactory = $this->prophesize(MetadataFactoryInterface::class);
        $factory = new CachedMetadataFactory($baseFactory->reveal(), $cacheItemPool->reveal());

        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItemPool
            ->getItem('Setono.SyliusMeilisearchPlugin.Tests.Unit.Document.Metadata.Document')
            ->willReturn($cacheItem)
        ;
        $cacheItem->isHit()->willReturn(true);
        $metadata = new Metadata(Document::class);
        $cacheItem->get()->willReturn($metadata);

        self::assertEquals(
            $metadata,
            $factory->getMetadataFor(Document::class),
        );
    }
}
