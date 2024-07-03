<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Webmozart\Assert\Assert;

final class IndexEntitiesHandler
{
    use ORMTrait;

    public function __construct(ManagerRegistry $managerRegistry, private readonly IndexRegistryInterface $indexRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(IndexEntities $message): void
    {
        $repository = $this->getRepository($message->class);

        /** @var mixed $entities */
        $entities = $repository->createQueryBuilder('o')
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $message->ids)
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($entities);
        Assert::allIsInstanceOf($entities, $message->class);

        foreach ($this->indexRegistry->getByEntity($message->class) as $index) {
            $index->indexer()->indexEntities($entities);
        }
    }
}
