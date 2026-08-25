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
    public function it_derives_the_rebuild_uid_from_the_live_uid_and_the_rebuild_id(): void
    {
        self::assertSame(
            'products__fashion_web__en_us__usd__rebuild_20260825120000_ab12cd',
            RebuildUid::from('products__fashion_web__en_us__usd', '20260825120000_ab12cd'),
        );
    }

    /**
     * @test
     */
    public function it_generates_unique_timestamped_ids(): void
    {
        $id = RebuildUid::generateId();

        self::assertMatchesRegularExpression('/^\d{14}_[0-9a-f]{6}$/', $id);
        self::assertNotSame($id, RebuildUid::generateId());
    }

    /**
     * @test
     */
    public function it_recognizes_rebuild_uids_of_a_live_uid(): void
    {
        self::assertTrue(RebuildUid::isRebuildOf(RebuildUid::from('products', RebuildUid::generateId()), 'products'));
        // A rebuild index of another live uid never matches, even when that uid shares a prefix
        self::assertFalse(RebuildUid::isRebuildOf(RebuildUid::from('products_extra', RebuildUid::generateId()), 'products'));
        self::assertFalse(RebuildUid::isRebuildOf('products', 'products'));
    }

    /**
     * @test
     */
    public function it_treats_a_freshly_generated_rebuild_index_as_not_stale(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertFalse(RebuildUid::isStale(RebuildUid::from('products', RebuildUid::generateId()), 'products', $now));
    }

    /**
     * @test
     */
    public function it_treats_a_day_old_rebuild_index_as_stale(): void
    {
        $now = new \DateTimeImmutable('2026-08-25 12:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(RebuildUid::isStale(RebuildUid::from('products', '20260824120000_ab12cd'), 'products', $now));
        self::assertFalse(RebuildUid::isStale(RebuildUid::from('products', '20260825090000_ab12cd'), 'products', $now));
    }

    /**
     * @test
     */
    public function it_treats_a_rebuild_index_with_an_unparseable_id_as_stale(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // e.g. the naming scheme of an older plugin version without per-run ids
        self::assertTrue(RebuildUid::isStale('products__rebuild', 'products', $now));
        self::assertTrue(RebuildUid::isStale('products__rebuild_garbage', 'products', $now));
    }

    /**
     * @test
     */
    public function it_never_treats_other_indexes_as_stale(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertFalse(RebuildUid::isStale('products', 'products', $now));
        self::assertFalse(RebuildUid::isStale('products_extra__rebuild_20200101120000_ab12cd', 'products', $now));
    }
}
