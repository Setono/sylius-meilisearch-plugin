<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\DataProvider\IndexableDataProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Message\Handler\IndexEntityHandler;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Message\Handler\IndexEntityHandler
 */
final class IndexEntityHandlerTest extends TestCase
{
    use ProphecyTrait;

    private function createIndex(IndexerInterface $indexer, IndexableDataProviderInterface $dataProvider): Index
    {
        $locator = new Container();
        $locator->set(IndexerInterface::class, $indexer);
        $locator->set(IndexableDataProviderInterface::class, $dataProvider);

        return new Index('products', ProductDocument::class, [Product::class], $locator);
    }

    /**
     * @test
     */
    public function it_indexes_the_entity_when_the_data_provider_contains_its_id(): void
    {
        $entity = $this->prophesize(IndexableInterface::class)->reveal();

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->indexEntity($entity)->shouldBeCalled();
        $indexer->removeEntity(Argument::cetera())->shouldNotBeCalled();

        $dataProvider = $this->prophesize(IndexableDataProviderInterface::class);
        $dataProvider->containsId(Product::class, Argument::type(Index::class), 42)->willReturn(true);

        $handler = $this->createHandler($entity, $indexer->reveal(), $dataProvider->reveal());
        $handler(new IndexEntity(Product::class, 42, '42'));
    }

    /**
     * @test
     */
    public function it_removes_the_entity_when_the_data_provider_does_not_contain_its_id(): void
    {
        $entity = $this->prophesize(IndexableInterface::class)->reveal();

        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->removeEntity($entity)->shouldBeCalled();
        $indexer->indexEntity(Argument::cetera())->shouldNotBeCalled();

        $dataProvider = $this->prophesize(IndexableDataProviderInterface::class);
        $dataProvider->containsId(Product::class, Argument::type(Index::class), 42)->willReturn(false);

        $handler = $this->createHandler($entity, $indexer->reveal(), $dataProvider->reveal());
        $handler(new IndexEntity(Product::class, 42, '42'));
    }

    /**
     * @test
     */
    public function it_throws_when_the_entity_is_not_found(): void
    {
        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getByEntity(Argument::cetera())->shouldNotBeCalled();

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Product::class, 42)->willReturn(null);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Product::class)->willReturn($manager->reveal());

        $handler = new IndexEntityHandler($managerRegistry->reveal(), $indexRegistry->reveal());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(new IndexEntity(Product::class, 42, '42'));
    }

    /**
     * @test
     */
    public function it_throws_when_the_id_is_not_scalar(): void
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::cetera())->shouldNotBeCalled();

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getByEntity(Argument::cetera())->shouldNotBeCalled();

        $handler = new IndexEntityHandler($managerRegistry->reveal(), $indexRegistry->reveal());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(new IndexEntity(Product::class, ['not', 'scalar'], null));
    }

    private function createHandler(
        IndexableInterface $entity,
        IndexerInterface $indexer,
        IndexableDataProviderInterface $dataProvider,
    ): IndexEntityHandler {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Product::class, 42)->willReturn($entity);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Product::class)->willReturn($manager->reveal());

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getByEntity(Product::class)->willReturn(['products' => $this->createIndex($indexer, $dataProvider)]);

        return new IndexEntityHandler($managerRegistry->reveal(), $indexRegistry->reveal());
    }
}
