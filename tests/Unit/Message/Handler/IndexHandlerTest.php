<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Nyholm\Psr7\Response;
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

    private static function notFound(): ApiException
    {
        return new ApiException(new Response(404), null);
    }

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
     * @test
     */
    public function it_rebuilds_into_the_rebuild_indexes_and_finalizes_after_indexing(): void
    {
        $calls = [];

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->index(true)->shouldBeCalledOnce()->will(function () use (&$calls): void {
            $calls[] = 'index';
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

        $client = $this->prophesize(Client::class);
        // No leftover rebuild indexes exist...
        $client->getRawIndex('products__a__rebuild')->willThrow(self::notFound());
        $client->getRawIndex('products__b__rebuild')->willThrow(self::notFound());
        // ...so nothing is deleted — and the live indexes are never deleted either (this is the
        // zero-downtime guarantee: the only mutation of a live index is the atomic swap)
        $client->deleteIndex(Argument::any())->shouldNotBeCalled();
        // The settings go to the rebuild indexes, once per unique uid
        $client->index('products__a__rebuild')->willReturn($this->prophesizeIndexes()->reveal());
        $client->index('products__b__rebuild')->willReturn($this->prophesizeIndexes()->reveal());

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(Argument::that(
                static fn (FinalizeIndexRebuild $message): bool => 'products' === $message->index && ['products__a', 'products__b'] === $message->liveUids,
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

        // The deprecated delete flag must be ignored: no deleteIndex call is expected above
        $handler(new IndexMessage('products', true));

        // The finalize message must be dispatched only after all batches have been dispatched
        self::assertSame(['index', 'finalize'], $calls);
    }

    /**
     * @test
     */
    public function it_deletes_a_leftover_rebuild_index_before_rebuilding(): void
    {
        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->index(true)->shouldBeCalledOnce();

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

        $client = $this->prophesize(Client::class);
        // A rebuild index was left behind by a previously failed rebuild
        $client->getRawIndex('products__a__rebuild')->willReturn(['uid' => 'products__a__rebuild']);
        $client->deleteIndex('products__a__rebuild')->shouldBeCalledOnce()->willReturn([]);
        $client->index('products__a__rebuild')->willReturn($this->prophesizeIndexes()->reveal());

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
        $indexer->index(Argument::cetera())->shouldNotBeCalled();

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
