<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\IndexesQuery;
use Meilisearch\Contracts\IndexesResults;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index as IndexMessage;
use Setono\SyliusMeilisearchPlugin\Message\Handler\IndexHandler;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Provider\Settings\SettingsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Message\Handler\IndexHandler
 */
final class IndexHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return ObjectProphecy<Indexes>
     */
    private function prophesizeIndexes(): ObjectProphecy
    {
        $indexes = $this->prophesize(Indexes::class);
        $indexes->updateSettings(['normalized' => 'settings'])->shouldBeCalledOnce()->willReturn([]);

        return $indexes;
    }

    /**
     * @param list<string> $uids
     */
    private function createIndexesResults(array $uids): IndexesResults
    {
        $results = [];
        foreach ($uids as $uid) {
            $index = $this->prophesize(Indexes::class);
            $index->getUid()->willReturn($uid);
            $results[] = $index->reveal();
        }

        return new IndexesResults(['results' => $results, 'offset' => 0, 'limit' => 200, 'total' => count($results)]);
    }

    /**
     * @test
     */
    public function it_rebuilds_into_per_run_rebuild_indexes_and_finalizes_after_indexing(): void
    {
        $calls = [];
        $settingsUids = [];

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->index(Argument::type('string'))->shouldBeCalledOnce()->will(function () use (&$calls): int {
            $calls[] = 'index';

            return 5;
        });

        $locator = new Container();
        $locator->set(IndexerInterface::class, $indexer->reveal());
        $index = new Index('products', ProductDocument::class, [Product::class], $locator);

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->get('products')->willReturn($index);

        // Two scopes resolving to the same uid plus a third scope: the rebuild must deduplicate
        $scopeA1 = new IndexScope($index, 'FASHION_WEB', 'en_US', null);
        $scopeA2 = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');
        $scopeB = new IndexScope($index, 'FASHION_WEB', 'da_DK', null);

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$scopeA1, $scopeA2, $scopeB]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($scopeA1)->willReturn('products__a');
        $uidResolver->resolveFromIndexScope($scopeA2)->willReturn('products__a');
        $uidResolver->resolveFromIndexScope($scopeB)->willReturn('products__b');

        $settingsProvider = $this->prophesize(SettingsProviderInterface::class);
        $settingsProvider->getSettings(Argument::type(IndexScope::class))->willReturn(new Settings());

        $normalizer = $this->prophesize(NormalizerInterface::class);
        $normalizer->normalize(Argument::type(Settings::class))->willReturn(['normalized' => 'settings']);

        $settingsIndexes = [$this->prophesizeIndexes()->reveal(), $this->prophesizeIndexes()->reveal()];

        $client = $this->prophesize(Client::class);
        // No stray rebuild indexes exist, and the live indexes are never deleted (this is the
        // zero-downtime guarantee: the only mutation of a live index is the atomic swap)
        $client->getIndexes(Argument::type(IndexesQuery::class))->willReturn($this->createIndexesResults([]));
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();
        // The settings go to the per-run rebuild indexes, once per unique uid
        $client
            ->index(Argument::that(function (string $uid) use (&$settingsUids): bool {
                if (1 !== preg_match('/^products__(a|b)__rebuild_\d{14}_[0-9a-f]{6}$/', $uid)) {
                    return false;
                }
                $settingsUids[] = $uid;

                return true;
            }))
            ->shouldBeCalledTimes(2)
            ->will(function () use (&$settingsIndexes): Indexes {
                $indexes = array_shift($settingsIndexes);
                \assert($indexes instanceof Indexes);

                return $indexes;
            })
        ;

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(Argument::that(
                function (FinalizeIndexRebuild $message) use (&$settingsUids): bool {
                    // The finalize message must carry the same rebuild id the settings were applied under
                    $sameGeneration = [] !== $settingsUids && RebuildUid::from('products__a', $message->rebuildId) === $settingsUids[0];

                    // 5 batches × 3 scopes (scope iterations, not unique uids — the indexer creates
                    // one document-addition task per scope iteration)
                    return 'products' === $message->index &&
                        ['products__a', 'products__b'] === $message->liveUids &&
                        15 === $message->expectedTasks &&
                        $sameGeneration;
                },
            ))
            ->shouldBeCalledOnce()
            ->will(function () use (&$calls): Envelope {
                $calls[] = 'finalize';

                return new Envelope(new \stdClass());
            })
        ;

        $handler = new IndexHandler(
            $indexRegistry->reveal(),
            $client->reveal(),
            $settingsProvider->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $normalizer->reveal(),
            $commandBus->reveal(),
        );

        $handler(new IndexMessage('products'));

        // The finalize message must be dispatched only after all batches have been dispatched
        self::assertSame(['index', 'finalize'], $calls);
    }

    /**
     * @test
     */
    public function it_deletes_stale_rebuild_indexes_but_never_a_running_generation(): void
    {
        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->index(Argument::type('string'))->shouldBeCalledOnce()->willReturn(1);

        $locator = new Container();
        $locator->set(IndexerInterface::class, $indexer->reveal());
        $index = new Index('products', ProductDocument::class, [Product::class], $locator);

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->get('products')->willReturn($index);

        $scope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$scope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($scope)->willReturn('products__a');

        $settingsProvider = $this->prophesize(SettingsProviderInterface::class);
        $settingsProvider->getSettings($scope)->willReturn(new Settings());

        $normalizer = $this->prophesize(NormalizerInterface::class);
        $normalizer->normalize(Argument::type(Settings::class))->willReturn(['normalized' => 'settings']);

        $staleUid = 'products__a__rebuild_20200101120000_ab12cd';
        // A concurrent rebuild started moments ago must not have its generation deleted
        $runningUid = RebuildUid::from('products__a', RebuildUid::generateId());

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::type(IndexesQuery::class))->willReturn(
            $this->createIndexesResults(['products__a', $staleUid, $runningUid]),
        );
        $client->deleteIndex($staleUid)->shouldBeCalledOnce()->willReturn([]);
        $client
            ->index(Argument::that(static fn (string $uid): bool => 1 === preg_match('/^products__a__rebuild_\d{14}_[0-9a-f]{6}$/', $uid)))
            ->willReturn($this->prophesizeIndexes()->reveal())
        ;

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::type(FinalizeIndexRebuild::class))->shouldBeCalledOnce()->willReturn(new Envelope(new \stdClass()));

        $handler = new IndexHandler(
            $indexRegistry->reveal(),
            $client->reveal(),
            $settingsProvider->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $normalizer->reveal(),
            $commandBus->reveal(),
        );

        $handler(new IndexMessage('products'));
    }

    /**
     * @test
     */
    public function it_does_not_index_or_finalize_when_there_are_no_scopes(): void
    {
        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->index(Argument::any())->shouldNotBeCalled();

        $locator = new Container();
        $locator->set(IndexerInterface::class, $indexer->reveal());
        $index = new Index('products', ProductDocument::class, [Product::class], $locator);

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->get('products')->willReturn($index);

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([]);

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $handler = new IndexHandler(
            $indexRegistry->reveal(),
            $this->prophesize(Client::class)->reveal(),
            $this->prophesize(SettingsProviderInterface::class)->reveal(),
            $indexScopeProvider->reveal(),
            $this->prophesize(IndexUidResolverInterface::class)->reveal(),
            $this->prophesize(NormalizerInterface::class)->reveal(),
            $commandBus->reveal(),
        );

        $handler(new IndexMessage('products'));
    }

    /**
     * @test
     */
    public function it_throws_an_unrecoverable_exception_when_the_index_is_not_configured(): void
    {
        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->get('unknown')->willThrow(new \InvalidArgumentException('No index exists with the name "unknown"'));

        $handler = new IndexHandler(
            $indexRegistry->reveal(),
            $this->prophesize(Client::class)->reveal(),
            $this->prophesize(SettingsProviderInterface::class)->reveal(),
            $this->prophesize(IndexScopeProviderInterface::class)->reveal(),
            $this->prophesize(IndexUidResolverInterface::class)->reveal(),
            $this->prophesize(NormalizerInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new IndexMessage('unknown'));
    }
}
