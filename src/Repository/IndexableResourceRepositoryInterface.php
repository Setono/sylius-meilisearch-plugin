<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<IndexableInterface&ResourceInterface>
 */
interface IndexableResourceRepositoryInterface extends RepositoryInterface
{
    public function createIndexableCollectionQueryBuilder(): QueryBuilder;

    /**
     * @param list<mixed> $ids
     *
     * @return list<IndexableInterface>
     */
    public function findFromIndexScopeAndIds(IndexScope $indexScope, array $ids): array;
}
