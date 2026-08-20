<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\IndexableOptionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<IndexableOptionInterface>
 */
interface IndexableOptionRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<array-key, IndexableOptionInterface>
     */
    public function findEnabledByIndex(string $index): array;
}
