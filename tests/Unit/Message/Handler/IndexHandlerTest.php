<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\IndexesQuery;
use Meilisearch\Contracts\IndexesResults;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;
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
use Setono\SyliusMeilisearchPlugin\Tests\Unit\Indexer\SpyLogger;
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

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->index(Argument::type(Rebuild::class))->shouldBeCalledOnce()->will(function () use (&$calls): int {
            $calls[] = 'index';

            return 5;
        });

        $locator = new Container();
        $locator->set(IndexerInterface::class, $indexer->reveal());
        $index = new Index('products', ProductDocument::class, [Product::class], $locator);

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->get('products')->willReturn($index);

        // Two scopes resolving to the same uid plus a third scope
        $scopeA1 = new IndexScope($index, 'FASHION_WEB', 'en_US', 'EUR');
        $scopeA2 = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');
        $scopeB = new IndexScope($index, 'FASHION_WEB', 'da_DK', null);

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$scopeA1, $scopeA2, $scopeB]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($scopeA1)->willReturn('products__a');
        $uidResolver->resolveFromIndexScope($scopeA2)->willReturn('products__a');
        $uidResolver->resolveFromIndexScope($scopeB)->willReturn('products__b');

        $settingsProvider = $this->prophesize(SettingsProviderInterface::class);
        $settingsProvider->getSettings(Argument::type(IndexScope::class))->will(fn (array $args): Settings => self::settingsFor($args[0]));

        $normalizer = $this->prophesize(NormalizerInterface::class);
        $normalizer->normalize(Argument::type(Settings::class))->will(function (array $args): array {
            \assert($args[0] instanceof Settings);

            return ['filterableAttributes' => [$args[0]->distinctAttribute]];
        });

        $client = $this->prophesize(Client::class);
        // No stray rebuild indexes exist, and the live indexes are never deleted (this is the
        // zero-downtime guarantee: the only mutation of a live index is the atomic swap)
        $client->getIndexes(Argument::type(IndexesQuery::class))->willReturn($this->createIndexesResults([]));
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();

        // The live index's settings are carried over, except the ones the plugin manages and the
        // embedders, whose API keys Meilisearch redacts
        $liveA = $this->prophesize(Indexes::class);
        $liveA->getSettings()->shouldBeCalledOnce()->willReturn([
            'filterableAttributes' => ['stale'],
            'localizedAttributes' => [['attributePatterns' => ['*_da'], 'locales' => ['dan']]],
            'embedders' => ['default' => ['source' => 'openAi', 'apiKey' => 'sk-XXXXXX...']],
        ]);
        $liveA->updateSettings(Argument::any())->shouldNotBeCalled();
        $client->index('products__a')->willReturn($liveA->reveal());
        // On the first rebuild the live index does not exist yet
        $liveB = $this->prophesize(Indexes::class);
        $liveB->getSettings()->willThrow(new ApiException(new Response(404), null));
        $client->index('products__b')->willReturn($liveB->reveal());

        // Settings are only ever applied to this run's rebuild indexes. Every scope's settings are
        // applied, in scope order, so the last scope sharing a uid wins.
        $localizedAttributes = [['attributePatterns' => ['*_da'], 'locales' => ['dan']]];
        $recorder = new class() {
            /** @var list<string> */
            public array $settings = [];

            /** @var list<string> */
            public array $rebuildUids = [];

            public ?FinalizeIndexRebuild $finalize = null;
        };

        $rebuildA = $this->prophesize(Indexes::class);
        foreach (['EUR', 'USD'] as $currency) {
            $rebuildA
                ->updateSettings(['filterableAttributes' => [$currency], 'localizedAttributes' => $localizedAttributes])
                ->shouldBeCalledOnce()
                ->will(function () use ($recorder, $currency): array {
                    $recorder->settings[] = $currency;

                    return [];
                })
            ;
        }
        $rebuildB = $this->prophesize(Indexes::class);
        $rebuildB->updateSettings(['filterableAttributes' => ['default']])->shouldBeCalledOnce()->willReturn([]);

        foreach (['a' => $rebuildA, 'b' => $rebuildB] as $scope => $rebuildIndex) {
            $client
                ->index(Argument::that(static fn (string $uid): bool => 1 === preg_match('/^products__' . $scope . '__rebuild_\d{14}_[0-9a-f]{6}_[0-9a-f]{4}$/', $uid)))
                ->will(function (array $args) use ($recorder, $rebuildIndex): Indexes {
                    \assert(is_string($args[0]));
                    $recorder->rebuildUids[] = $args[0];

                    return $rebuildIndex->reveal();
                })
            ;
        }

        $message = new IndexMessage('products');

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(Argument::that(
                static fn (FinalizeIndexRebuild $finalize): bool => 'products' === $finalize->index &&
                    ['products__a', 'products__b'] === $finalize->rebuild->liveUids &&
                    5 === $finalize->batches &&
                    $message->requestId === RebuildUid::requestIdOf($finalize->rebuild->uidOf('products__a')),
            ))
            ->shouldBeCalledOnce()
            ->will(function (array $args) use (&$calls, $recorder): Envelope {
                \assert($args[0] instanceof FinalizeIndexRebuild);
                $recorder->finalize = $args[0];
                $calls[] = 'finalize';

                return new Envelope(new \stdClass());
            })
        ;

        $logger = new SpyLogger();

        $handler = new IndexHandler(
            $indexRegistry->reveal(),
            $client->reveal(),
            $settingsProvider->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $normalizer->reveal(),
            $commandBus->reveal(),
            $logger,
        );

        $handler($message);

        // The finalize message must be dispatched only after all batches have been dispatched
        self::assertSame(['index', 'finalize'], $calls);

        self::assertSame(['EUR', 'USD'], $recorder->settings);
        // The finalization targets the generation of rebuild indexes the settings were applied to
        self::assertNotNull($recorder->finalize);
        self::assertSame($recorder->finalize->rebuild->uids(), array_values(array_unique($recorder->rebuildUids)));

        $warning = $logger->firstOfLevel('warning');
        self::assertNotNull($warning);
        self::assertSame('products__a', $warning['context']['index']);
    }

    private static function settingsFor(mixed $indexScope): Settings
    {
        \assert($indexScope instanceof IndexScope);

        // Tells the scopes' settings apart
        $settings = new Settings();
        $settings->distinctAttribute = $indexScope->currencyCode ?? 'default';

        return $settings;
    }

    /**
     * @test
     */
    public function it_deletes_stale_rebuild_indexes_but_never_a_running_generation(): void
    {
        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->index(Argument::type(Rebuild::class))->shouldBeCalledOnce()->willReturn(1);

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

        $staleUid = 'products__a__rebuild_20200101120000_ab12cd_ef01';
        $staleLedgerUid = $staleUid . '__ledger';
        // A concurrent rebuild started moments ago must not have its generation deleted
        $runningUid = RebuildUid::from('products__a', RebuildUid::generateId('123456'));

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::type(IndexesQuery::class))->willReturn(
            $this->createIndexesResults(['products__a', $staleUid, $staleLedgerUid, $runningUid]),
        );
        $client->deleteIndex($staleUid)->shouldBeCalledOnce()->willReturn([]);
        $client->deleteIndex($staleLedgerUid)->shouldBeCalledOnce()->willReturn([]);

        $live = $this->prophesize(Indexes::class);
        $live->getSettings()->willReturn([]);
        $client->index('products__a')->willReturn($live->reveal());

        $rebuildIndex = $this->prophesize(Indexes::class);
        $rebuildIndex->updateSettings(['normalized' => 'settings'])->shouldBeCalledOnce()->willReturn([]);
        $client
            ->index(Argument::that(static fn (string $uid): bool => str_starts_with($uid, 'products__a__rebuild_')))
            ->willReturn($rebuildIndex->reveal())
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
