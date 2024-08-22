<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataProvider;

use Doctrine\Persistence\ManagerRegistry;
use DoctrineBatchUtils\BatchProcessing\SelectBatchIteratorAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;

final class DefaultIndexableDataProvider implements IndexableDataProviderInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getIds(string $entity, Index $index): \Generator
    {
        $qb = $this
            ->getManager($entity)
            ->createQueryBuilder()
            ->select('o.id')
            ->from($entity, 'o')
        ;

        $this->eventDispatcher->dispatch(new QueryBuilderForDataProvisionCreated($entity, $index, $qb));

        /** @var SelectBatchIteratorAggregate<array-key, string|int> $batch */
        $batch = SelectBatchIteratorAggregate::fromQuery($qb->getQuery(), 100);

        yield from $batch;
    }
}
