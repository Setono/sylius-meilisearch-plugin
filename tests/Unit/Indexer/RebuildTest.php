<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Indexer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Indexer\Rebuild
 */
final class RebuildTest extends TestCase
{
    /**
     * @test
     */
    public function it_derives_the_uids_of_the_run(): void
    {
        $rebuild = new Rebuild('20260825120000_ab12cd_ef01', ['products__a', 'products__b']);

        self::assertSame('products__a__rebuild_20260825120000_ab12cd_ef01', $rebuild->uidOf('products__a'));
        self::assertSame(
            ['products__a__rebuild_20260825120000_ab12cd_ef01', 'products__b__rebuild_20260825120000_ab12cd_ef01'],
            $rebuild->uids(),
        );
        // One ledger per run
        self::assertSame('products__a__rebuild_20260825120000_ab12cd_ef01__ledger', $rebuild->ledgerUid());
    }

    /**
     * @test
     */
    public function it_covers_only_its_frozen_live_uids(): void
    {
        $rebuild = new Rebuild('20260825120000_ab12cd_ef01', ['products__a']);

        self::assertTrue($rebuild->covers('products__a'));
        self::assertFalse($rebuild->covers('products__b'));
    }
}
