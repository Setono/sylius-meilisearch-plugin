<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\Rebuild;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Setono\SyliusMeilisearchPlugin\Message\Handler\IndexEntitiesHandler;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Message\Handler\IndexEntitiesHandler
 */
final class IndexEntitiesHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const REBUILD_ID = '20260825120000_ab12cd_ef01';

    /**
     * @test
     */
    public function it_records_a_rebuild_batch_in_the_ledger_after_indexing_it(): void
    {
        $calls = [];

        $product = new Product();
        $rebuild = new Rebuild(self::REBUILD_ID, ['products__a']);

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->indexEntities([$product], $rebuild)->shouldBeCalledOnce()->will(function () use (&$calls): void {
            $calls[] = 'index';
        });

        $ledger = $this->prophesize(Indexes::class);
        $ledger->addDocuments([['id' => 3]], 'id')->shouldBeCalledOnce()->will(function () use (&$calls): array {
            $calls[] = 'record';

            return [];
        });

        $client = $this->prophesize(Client::class);
        $client->index('products__a__rebuild_' . self::REBUILD_ID . '__ledger')->willReturn($ledger->reveal());

        $handler = $this->createHandler([$product], $indexer, $client);
        $handler(IndexEntities::fromIds(Product::class, [1], 'products', $rebuild, 3));

        // The batch only counts as done once all its documents have been handed to Meilisearch
        self::assertSame(['index', 'record'], $calls);
    }

    /**
     * @test
     */
    public function it_records_a_rebuild_batch_whose_entities_no_longer_exist(): void
    {
        // e.g. the batch's only product was deleted before the batch was handled — the rebuild must
        // still be able to complete
        $rebuild = new Rebuild(self::REBUILD_ID, ['products__a']);

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->indexEntities([], $rebuild)->shouldBeCalledOnce();

        $ledger = $this->prophesize(Indexes::class);
        $ledger->addDocuments([['id' => 7]], 'id')->shouldBeCalledOnce()->willReturn([]);

        $client = $this->prophesize(Client::class);
        $client->index('products__a__rebuild_' . self::REBUILD_ID . '__ledger')->willReturn($ledger->reveal());

        $handler = $this->createHandler([], $indexer, $client);
        $handler(IndexEntities::fromIds(Product::class, [1], 'products', $rebuild, 7));
    }

    /**
     * @test
     */
    public function it_records_nothing_outside_a_rebuild(): void
    {
        $product = new Product();

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->indexEntities([$product], null)->shouldBeCalledOnce();

        $client = $this->prophesize(Client::class);
        $client->index(Argument::any())->shouldNotBeCalled();

        $handler = $this->createHandler([$product], $indexer, $client);
        $handler(IndexEntities::fromIds(Product::class, [1], 'products'));
    }

    /**
     * @param list<Product> $entities
     * @param ObjectProphecy<IndexerInterface> $indexer
     * @param ObjectProphecy<Client> $client
     */
    private function createHandler(array $entities, ObjectProphecy $indexer, ObjectProphecy $client): IndexEntitiesHandler
    {
        $query = $this->prophesize(Query::class);
        $query->getResult()->willReturn($entities);

        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $queryBuilder->andWhere(Argument::any())->willReturn($queryBuilder->reveal());
        $queryBuilder->setParameter(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $repository = $this->prophesize(EntityRepository::class);
        $repository->createQueryBuilder('o')->willReturn($queryBuilder->reveal());

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->getRepository(Product::class)->willReturn($repository->reveal());
        $manager->clear()->shouldBeCalled();

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Product::class)->willReturn($manager->reveal());

        $locator = new Container();
        $locator->set(IndexerInterface::class, $indexer->reveal());

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->get('products')->willReturn(new Index('products', ProductDocument::class, [Product::class], $locator));

        return new IndexEntitiesHandler($managerRegistry->reveal(), $indexRegistry->reveal(), $client->reveal());
    }
}
