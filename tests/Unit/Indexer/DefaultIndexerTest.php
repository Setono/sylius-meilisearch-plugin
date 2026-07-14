<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Indexer;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EntityFilterInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\DefaultIndexer;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;
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
        $indexes->addDocuments([], 'id')->shouldBeCalled()->willReturn([]);
        // The filtered-out entity is removed from the index
        $indexes->deleteDocuments(['42'])->shouldBeCalledOnce()->willReturn([]);

        $client = $this->prophesize(Client::class);
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
}
