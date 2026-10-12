<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\IndexesQuery;
use Meilisearch\Contracts\IndexesResults;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusMeilisearchPlugin\Meilisearch\RebuildIndexes;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\RebuildIndexes
 */
final class RebuildIndexesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_lists_every_index_across_all_pages(): void
    {
        $client = $this->prophesize(Client::class);
        $client
            ->getIndexes(Argument::that(static fn (IndexesQuery $query): bool => 0 === ($query->toArray()['offset'] ?? null)))
            ->willReturn($this->createIndexesResults(['a', 'b'], 0, 201))
        ;
        $client
            ->getIndexes(Argument::that(static fn (IndexesQuery $query): bool => 200 === ($query->toArray()['offset'] ?? null)))
            ->willReturn($this->createIndexesResults(['c'], 200, 201))
        ;

        self::assertSame(['a', 'b', 'c'], RebuildIndexes::all($client->reveal()));
    }

    /**
     * @test
     */
    public function it_returns_the_rebuild_indexes_of_rebuilds_in_flight(): void
    {
        $running = RebuildUid::generateId('ab12cd');

        $client = $this->prophesizeClient([
            'products__a',
            'products__b',
            RebuildUid::from('products__a', $running),
            // The ledger holds no documents, and a stale rebuild belongs to an abandoned run
            RebuildUid::ledger('products__a', $running),
            RebuildUid::from('products__a', '20200101120000_ab12cd_ef01'),
            RebuildUid::from('products__b', $running),
            RebuildUid::from('other__a', $running),
        ]);

        self::assertSame(
            [
                'products__a' => [RebuildUid::from('products__a', $running)],
                'products__b' => [RebuildUid::from('products__b', $running)],
            ],
            RebuildIndexes::inFlight($client->reveal(), ['products__a', 'products__b']),
        );
    }

    /**
     * @test
     */
    public function it_does_not_list_the_indexes_without_live_uids(): void
    {
        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], RebuildIndexes::inFlight($client->reveal(), []));
    }

    /**
     * @param list<string> $uids
     *
     * @return ObjectProphecy<Client>
     */
    private function prophesizeClient(array $uids): ObjectProphecy
    {
        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::type(IndexesQuery::class))->willReturn($this->createIndexesResults($uids, 0, count($uids)));

        return $client;
    }

    /**
     * @param list<string> $uids
     */
    private function createIndexesResults(array $uids, int $offset, int $total): IndexesResults
    {
        $results = [];
        foreach ($uids as $uid) {
            $index = $this->prophesize(Indexes::class);
            $index->getUid()->willReturn($uid);
            $results[] = $index->reveal();
        }

        return new IndexesResults(['results' => $results, 'offset' => $offset, 'limit' => 200, 'total' => $total]);
    }
}
