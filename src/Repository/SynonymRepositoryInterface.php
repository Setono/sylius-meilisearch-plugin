<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<SynonymInterface>
 */
interface SynonymRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<array-key, SynonymInterface>
     */
    public function findEnabledByIndexScope(IndexScope $indexScope): array;
}
