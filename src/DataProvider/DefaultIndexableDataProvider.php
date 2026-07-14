<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataProvider;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineBatchUtils\BatchProcessing\SelectBatchIteratorAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class DefaultIndexableDataProvider implements IndexableDataProviderInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @return \Generator<array-key, string|int>
     */
    public function getIds(string $entity, Index $index): \Generator
    {
        $qb = $this->createQueryBuilder($entity, $index);

        /** @var SelectBatchIteratorAggregate<array-key, string|int> $batch */
        $batch = SelectBatchIteratorAggregate::fromQuery($qb->getQuery(), 100);

        yield from $batch;
    }

    public function containsId(string $entity, Index $index, int|string $id): bool
    {
        $qb = $this->createQueryBuilder($entity, $index)
            ->andWhere('o.id = :setono_sylius_meilisearch_id')
            ->setParameter('setono_sylius_meilisearch_id', $id)
            ->setMaxResults(1)
        ;

        return [] !== $qb->getQuery()->getScalarResult();
    }

    /**
     * Builds the base query used by both getIds() and containsId() and dispatches the filter event on
     * it. The id constraint (if any) is added by the caller _after_ this returns, so every subscriber
     * sees the exact same query shape on both paths — this is what keeps the two consistent.
     *
     * @param class-string<IndexableInterface> $entity
     */
    private function createQueryBuilder(string $entity, Index $index): QueryBuilder
    {
        $qb = $this
            ->getManager($entity)
            ->createQueryBuilder()
            ->select('DISTINCT o.id')
            ->from($entity, 'o')
        ;

        $this->eventDispatcher->dispatch(new QueryBuilderForDataProvisionCreated($entity, $index, $qb));

        return $qb;
    }
}
