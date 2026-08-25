<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\IndexableSubjectInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @template T of IndexableSubjectInterface
 * @extends RepositoryInterface<T>
 */
interface IndexableSubjectRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<array-key, T>
     */
    public function findEnabledByIndex(string $index): array;
}
