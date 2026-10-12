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
    private const ID = '20260825120000_ab12cd_ef01';

    /**
     * @test
     */
    public function it_derives_the_rebuild_and_ledger_uids_from_the_live_uid_and_the_rebuild_id(): void
    {
        self::assertSame(
            'products__fashion_web__en_us__usd__rebuild_20260825120000_ab12cd_ef01',
            RebuildUid::from('products__fashion_web__en_us__usd', self::ID),
        );
        self::assertSame(
            'products__fashion_web__en_us__usd__rebuild_20260825120000_ab12cd_ef01__ledger',
            RebuildUid::ledger('products__fashion_web__en_us__usd', self::ID),
        );
    }

    /**
     * @test
     */
    public function it_generates_unique_timestamped_ids_embedding_the_request_id(): void
    {
        $requestId = RebuildUid::generateRequestId();
        self::assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $requestId);

        $id = RebuildUid::generateId($requestId);

        self::assertMatchesRegularExpression('/^\d{14}_' . $requestId . '_[0-9a-f]{4}$/', $id);
        // A retried request builds into new indexes
        self::assertNotSame($id, RebuildUid::generateId($requestId));
    }

    /**
     * @test
     */
    public function it_recognizes_rebuild_uids_of_a_live_uid(): void
    {
        self::assertTrue(RebuildUid::isRebuildOf(RebuildUid::from('products', self::ID), 'products'));
        self::assertTrue(RebuildUid::isRebuildOf(RebuildUid::ledger('products', self::ID), 'products'));
        // A rebuild index of another live uid never matches, even when that uid shares a prefix
        self::assertFalse(RebuildUid::isRebuildOf(RebuildUid::from('products_extra', self::ID), 'products'));
        self::assertFalse(RebuildUid::isRebuildOf('products', 'products'));
    }

    /**
     * @test
     */
    public function it_extracts_the_rebuild_id(): void
    {
        self::assertSame(self::ID, RebuildUid::idOf(RebuildUid::from('products', self::ID), 'products'));
        self::assertSame(self::ID, RebuildUid::idOf(RebuildUid::ledger('products', self::ID), 'products'));
        self::assertNull(RebuildUid::idOf(RebuildUid::from('products_extra', self::ID), 'products'));
        self::assertNull(RebuildUid::idOf('products', 'products'));
    }

    /**
     * @test
     */
    public function it_orders_rebuild_ids_by_their_start(): void
    {
        self::assertGreaterThan(0, strcmp('20260825120001_000000_0000', '20260825120000_ffffff_ffff'));
    }

    /**
     * @test
     */
    public function it_extracts_the_request_id(): void
    {
        self::assertSame('ab12cd', RebuildUid::requestIdOf(RebuildUid::from('products__fashion_web', self::ID)));
        self::assertSame('ab12cd', RebuildUid::requestIdOf(RebuildUid::ledger('products__fashion_web', self::ID)));
        self::assertNull(RebuildUid::requestIdOf('products__fashion_web'));
        self::assertNull(RebuildUid::requestIdOf('products__fashion_web__rebuild_garbage'));
    }

    /**
     * @test
     */
    public function it_extracts_the_start_of_the_run(): void
    {
        self::assertEquals(
            new \DateTimeImmutable('2026-08-25 12:00:00', new \DateTimeZone('UTC')),
            RebuildUid::startedAt(self::ID),
        );
        self::assertNull(RebuildUid::startedAt('garbage'));
    }

    /**
     * @test
     */
    public function it_treats_a_freshly_generated_rebuild_index_as_not_stale(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertFalse(RebuildUid::isStale(RebuildUid::from('products', RebuildUid::generateId('ab12cd')), 'products', $now));
    }

    /**
     * @test
     */
    public function it_treats_a_day_old_rebuild_index_as_stale(): void
    {
        $now = new \DateTimeImmutable('2026-08-26 12:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(RebuildUid::isStale(RebuildUid::from('products', self::ID), 'products', $now));
        self::assertTrue(RebuildUid::isStale(RebuildUid::ledger('products', self::ID), 'products', $now));
        self::assertFalse(RebuildUid::isStale(RebuildUid::from('products', '20260826090000_ab12cd_ef01'), 'products', $now));
    }

    /**
     * @test
     */
    public function it_treats_a_rebuild_index_with_an_unparseable_id_as_stale(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // e.g. the naming schemes of earlier development versions
        self::assertTrue(RebuildUid::isStale('products__rebuild', 'products', $now));
        self::assertTrue(RebuildUid::isStale('products__rebuild_20260825120000_ab12cd', 'products', $now));
        self::assertTrue(RebuildUid::isStale('products__rebuild_garbage', 'products', $now));
    }

    /**
     * @test
     */
    public function it_never_treats_other_indexes_as_stale(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertFalse(RebuildUid::isStale('products', 'products', $now));
        self::assertFalse(RebuildUid::isStale('products_extra__rebuild_20200101120000_ab12cd_ef01', 'products', $now));
    }

    /**
     * @test
     */
    public function it_recognizes_ledger_uids(): void
    {
        self::assertTrue(RebuildUid::isLedger(RebuildUid::ledger('products', self::ID)));
        self::assertFalse(RebuildUid::isLedger(RebuildUid::from('products', self::ID)));
    }
}
