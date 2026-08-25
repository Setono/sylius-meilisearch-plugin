<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Resolver\IndexUid;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid
 */
final class RebuildUidTest extends TestCase
{
    /**
     * @test
     */
    public function it_derives_the_rebuild_uid_from_the_live_uid(): void
    {
        self::assertSame('products__fashion_web__en_us__usd__rebuild', RebuildUid::from('products__fashion_web__en_us__usd'));
    }
}
