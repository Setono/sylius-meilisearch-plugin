<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Indexer;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Meilisearch\Contracts\IndexesResults;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\DataProvider\IndexableDataProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EntityFilterInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer
 */
final class DefaultIndexerTest extends TestCase
{
    use ProphecyTrait;

    private const REBUILD_ID = '20260825120000_ab12cd_ef01';

    private function createIndexer(SpyLogger $logger, bool $passesFilter, ConstraintViolationList $violations): DefaultIndexer
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__test');

        $dataMapper = $this->prophesize(DataMapperInterface::class);

        $objectFilter = $this->prophesize(EntityFilterInterface::class);
        $objectFilter->filter(Argument::cetera())->willReturn($passesFilter);

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate(Argument::cetera())->willReturn($violations);

        $indexes = $this->prophesize(Indexes::class);
        $indexes->addDocuments(Argument::cetera())->willReturn([]);
        $indexes->deleteDocuments(Argument::cetera())->willReturn([]);

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::cetera())->willReturn($this->createIndexesResults());
        $client->index('products__test')->willReturn($indexes->reveal());

        return new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $dataMapper->reveal(),
            $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->reveal(),
            $client->reveal(),
            $objectFilter->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $validator->reveal(),
            $logger,
        );
    }

    private function createEntity(): IndexableInterface
    {
        $entity = $this->prophesize(IndexableInterface::class);
        $entity->getDocumentIdentifier()->willReturn('42');

        return $entity->reveal();
    }

    /**
     * @param list<string> $uids
     */
    private function createIndexesResults(array $uids = []): IndexesResults
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
    public function it_logs_a_warning_naming_the_entity_and_violations_when_a_document_is_invalid(): void
    {
        $logger = new SpyLogger();
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], '', 'name', null),
        ]);

        $indexer = $this->createIndexer($logger, passesFilter: true, violations: $violations);
        $indexer->indexEntities([$this->createEntity()]);

        $warning = $logger->firstOfLevel('warning');
        self::assertNotNull($warning);
        self::assertSame('42', $warning['context']['id']);
        self::assertSame(['name: This value should not be blank.'], $warning['context']['violations']);
    }

    /**
     * @test
     */
    public function it_logs_at_debug_when_an_entity_is_filtered_out(): void
    {
        $logger = new SpyLogger();

        $indexer = $this->createIndexer($logger, passesFilter: false, violations: new ConstraintViolationList());
        $indexer->indexEntities([$this->createEntity()]);

        $debug = $logger->firstOfLevel('debug');
        self::assertNotNull($debug);
        self::assertSame('42', $debug['context']['id']);
    }

    /**
     * @test
     */
    public function it_logs_a_batch_summary(): void
    {
        $logger = new SpyLogger();

        $indexer = $this->createIndexer($logger, passesFilter: false, violations: new ConstraintViolationList());
        $indexer->indexEntities([$this->createEntity()]);

        $summary = $logger->firstOfLevel('info');
        self::assertNotNull($summary);
        self::assertSame(1, $summary['context']['mapped']);
        self::assertSame(1, $summary['context']['filtered']);
        self::assertSame(0, $summary['context']['invalid']);
        self::assertSame(0, $summary['context']['indexed']);
    }

    /**
     * @test
     */
    public function it_does_not_create_an_add_documents_task_for_an_empty_batch(): void
    {
        $logger = new SpyLogger();

        // The single entity is filtered out, so no documents remain to index. createIndexer stubs
        // addDocuments, but here we assert that a valid-but-empty batch issues no addDocuments task.
        $indexer = $this->createIndexer($logger, passesFilter: true, violations: new ConstraintViolationList([
            new ConstraintViolation('invalid', null, [], '', 'name', null),
        ]));

        $indexer->indexEntities([$this->createEntity()]);

        $summary = $logger->firstOfLevel('info');
        self::assertNotNull($summary);
        self::assertSame(0, $summary['context']['indexed']);
    }

    /**
     * @test
     */
    public function it_removes_a_filtered_out_entity_from_the_index(): void
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__test');

        $objectFilter = $this->prophesize(EntityFilterInterface::class);
        $objectFilter->filter(Argument::cetera())->willReturn(false);

        $indexes = $this->prophesize(Indexes::class);
        // Nothing to index (the only entity was filtered out), so no addDocuments task is created
        $indexes->addDocuments(Argument::cetera())->shouldNotBeCalled();
        // The filtered-out entity is removed from the index
        $indexes->deleteDocuments(['42'])->shouldBeCalledOnce()->willReturn([]);

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::cetera())->willReturn($this->createIndexesResults());
        $client->index('products__test')->willReturn($indexes->reveal());

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->reveal(),
            $client->reveal(),
            $objectFilter->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $this->prophesize(ValidatorInterface::class)->reveal(),
            new SpyLogger(),
        );

        $indexer->indexEntities([$this->createEntity()]);
    }

    /**
     * @test
     */
    public function it_targets_the_rebuild_index_when_rebuilding(): void
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__test');

        $objectFilter = $this->prophesize(EntityFilterInterface::class);
        $objectFilter->filter(Argument::cetera())->willReturn(true);

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate(Argument::cetera())->willReturn(new ConstraintViolationList());

        $normalizer = $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class);
        $normalizer->normalize(Argument::cetera())->willReturn(['id' => '42']);

        $indexes = $this->prophesize(Indexes::class);
        $indexes->addDocuments([['id' => '42']], 'id')->shouldBeCalledOnce()->willReturn([]);

        $client = $this->prophesize(Client::class);
        // The documents go to the rebuild index only — not the live index, and no rebuilds in flight
        // are looked up
        $client->index(RebuildUid::from('products__test', self::REBUILD_ID))->willReturn($indexes->reveal());
        $client->index('products__test')->shouldNotBeCalled();
        $client->getIndexes(Argument::cetera())->shouldNotBeCalled();

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $normalizer->reveal(),
            $client->reveal(),
            $objectFilter->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $validator->reveal(),
            new SpyLogger(),
        );

        $indexer->indexEntities([$this->createEntity()], new Rebuild(self::REBUILD_ID, ['products__test']));
    }

    /**
     * @test
     */
    public function it_removes_a_filtered_out_entity_from_the_rebuild_index_too(): void
    {
        // On a retried batch the rebuild index may already contain a document for an entity that
        // has since been filtered out, so the removal must not be skipped in rebuild mode
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__test');

        $objectFilter = $this->prophesize(EntityFilterInterface::class);
        $objectFilter->filter(Argument::cetera())->willReturn(false);

        $indexes = $this->prophesize(Indexes::class);
        $indexes->addDocuments(Argument::cetera())->shouldNotBeCalled();
        $indexes->deleteDocuments(['42'])->shouldBeCalledOnce()->willReturn([]);

        $client = $this->prophesize(Client::class);
        $client->index(RebuildUid::from('products__test', self::REBUILD_ID))->willReturn($indexes->reveal());

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->reveal(),
            $client->reveal(),
            $objectFilter->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $this->prophesize(ValidatorInterface::class)->reveal(),
            new SpyLogger(),
        );

        $indexer->indexEntities([$this->createEntity()], new Rebuild(self::REBUILD_ID, ['products__test']));
    }

    /**
     * @test
     */
    public function it_dispatches_batches_scoped_to_its_own_index(): void
    {
        $dataProvider = $this->prophesize(IndexableDataProviderInterface::class);

        $locator = new Container();
        $locator->set(IndexableDataProviderInterface::class, $dataProvider->reveal());
        $index = new Index('products', ProductDocument::class, [Product::class], $locator);

        $dataProvider->getIds(Product::class, $index)->willReturn([1, 2]);

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(Argument::that(
                static fn (IndexEntities $message): bool => [1, 2] === $message->ids &&
                    'products' === $message->index &&
                    self::REBUILD_ID === $message->rebuild?->id &&
                    1 === $message->batch,
            ))
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $this->prophesize(IndexScopeProviderInterface::class)->reveal(),
            $this->prophesize(IndexUidResolverInterface::class)->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->reveal(),
            $this->prophesize(Client::class)->reveal(),
            $this->prophesize(EntityFilterInterface::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $commandBus->reveal(),
            $this->prophesize(ValidatorInterface::class)->reveal(),
            new SpyLogger(),
        );

        self::assertSame(1, $indexer->index(new Rebuild(self::REBUILD_ID, ['products__test'])));
    }

    /**
     * @test
     */
    public function it_removes_documents_with_one_batch_delete_task_per_scope(): void
    {
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__test');

        $indexes = $this->prophesize(Indexes::class);
        // A single batch deleteDocuments call for the whole set of ids, not one call per id
        $indexes->deleteDocuments(['1', '2', '3'])->shouldBeCalledOnce()->willReturn([]);

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::cetera())->willReturn($this->createIndexesResults());
        $client->index('products__test')->willReturn($indexes->reveal());

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->reveal(),
            $client->reveal(),
            $this->prophesize(EntityFilterInterface::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $this->prophesize(ValidatorInterface::class)->reveal(),
            new SpyLogger(),
        );

        $indexer->removeDocuments(['1', '2', '3']);
    }

    /**
     * @test
     */
    public function it_mirrors_changes_into_the_rebuild_indexes_of_rebuilds_in_flight(): void
    {
        // The rebuild index was populated from an earlier database state, so the swap would revert a
        // change written only to the live index
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__test');

        $objectFilter = $this->prophesize(EntityFilterInterface::class);
        $objectFilter->filter(Argument::cetera())->willReturn(true);

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate(Argument::cetera())->willReturn(new ConstraintViolationList());

        $normalizer = $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class);
        $normalizer->normalize(Argument::cetera())->willReturn(['id' => '42']);

        $rebuildUid = RebuildUid::from('products__test', RebuildUid::generateId('ab12cd'));

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::cetera())->willReturn($this->createIndexesResults([
            'products__test',
            $rebuildUid,
            RebuildUid::ledger('products__test', RebuildUid::generateId('ab12cd')),
        ]));
        foreach (['products__test', $rebuildUid] as $uid) {
            $indexes = $this->prophesize(Indexes::class);
            $indexes->addDocuments([['id' => '42']], 'id')->shouldBeCalledOnce()->willReturn([]);
            $client->index($uid)->willReturn($indexes->reveal());
        }

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $normalizer->reveal(),
            $client->reveal(),
            $objectFilter->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $validator->reveal(),
            new SpyLogger(),
        );

        $indexer->indexEntities([$this->createEntity()]);
    }

    /**
     * @test
     */
    public function it_removes_documents_from_the_rebuild_indexes_of_rebuilds_in_flight_too(): void
    {
        // Otherwise the swap would bring back a document indexed by the rebuild before its removal
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__test');

        $rebuildUid = RebuildUid::from('products__test', RebuildUid::generateId('ab12cd'));

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::cetera())->willReturn($this->createIndexesResults(['products__test', $rebuildUid]));
        foreach (['products__test', $rebuildUid] as $uid) {
            $indexes = $this->prophesize(Indexes::class);
            $indexes->deleteDocuments(['1'])->shouldBeCalledOnce()->willReturn([]);
            $client->index($uid)->willReturn($indexes->reveal());
        }

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->reveal(),
            $client->reveal(),
            $this->prophesize(EntityFilterInterface::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $this->prophesize(ValidatorInterface::class)->reveal(),
            new SpyLogger(),
        );

        $indexer->removeDocuments(['1']);
    }

    /**
     * @test
     */
    public function it_skips_scopes_that_appeared_after_the_rebuild_started(): void
    {
        // Such a scope has no rebuild index and is not part of the swap
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $existingScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');
        $newScope = new IndexScope($index, 'FASHION_WEB', 'da_DK', 'DKK');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$existingScope, $newScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($existingScope)->willReturn('products__test');
        $uidResolver->resolveFromIndexScope($newScope)->willReturn('products__new');

        $objectFilter = $this->prophesize(EntityFilterInterface::class);
        $objectFilter->filter(Argument::cetera())->willReturn(true);

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate(Argument::cetera())->willReturn(new ConstraintViolationList());

        $normalizer = $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class);
        $normalizer->normalize(Argument::cetera())->willReturn(['id' => '42']);

        $indexes = $this->prophesize(Indexes::class);
        $indexes->addDocuments([['id' => '42']], 'id')->shouldBeCalledOnce()->willReturn([]);

        $client = $this->prophesize(Client::class);
        $client->index(RebuildUid::from('products__test', self::REBUILD_ID))->willReturn($indexes->reveal());
        $client->index(RebuildUid::from('products__new', self::REBUILD_ID))->shouldNotBeCalled();

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $normalizer->reveal(),
            $client->reveal(),
            $objectFilter->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $validator->reveal(),
            new SpyLogger(),
        );

        $indexer->indexEntities([$this->createEntity()], new Rebuild(self::REBUILD_ID, ['products__test']));
    }

    /**
     * @test
     */
    public function it_numbers_the_batches_of_a_rebuild(): void
    {
        $dataProvider = $this->prophesize(IndexableDataProviderInterface::class);

        $locator = new Container();
        $locator->set(IndexableDataProviderInterface::class, $dataProvider->reveal());
        $index = new Index('products', ProductDocument::class, [Product::class], $locator);

        $dataProvider->getIds(Product::class, $index)->willReturn(range(1, 150));

        $batches = [];

        $commandBus = $this->prophesize(MessageBusInterface::class);
        $commandBus
            ->dispatch(Argument::type(IndexEntities::class))
            ->shouldBeCalledTimes(2)
            ->will(function (array $args) use (&$batches): Envelope {
                \assert($args[0] instanceof IndexEntities);
                $batches[] = $args[0]->batch;

                return new Envelope($args[0]);
            })
        ;

        $indexer = new DefaultIndexer(
            $index,
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $this->prophesize(IndexScopeProviderInterface::class)->reveal(),
            $this->prophesize(IndexUidResolverInterface::class)->reveal(),
            $this->prophesize(DataMapperInterface::class)->reveal(),
            $this->prophesize(\Symfony\Component\Serializer\Normalizer\NormalizerInterface::class)->reveal(),
            $this->prophesize(Client::class)->reveal(),
            $this->prophesize(EntityFilterInterface::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $commandBus->reveal(),
            $this->prophesize(ValidatorInterface::class)->reveal(),
            new SpyLogger(),
        );

        self::assertSame(2, $indexer->index(new Rebuild(self::REBUILD_ID, ['products__test'])));
        self::assertSame([1, 2], $batches);
    }
}
