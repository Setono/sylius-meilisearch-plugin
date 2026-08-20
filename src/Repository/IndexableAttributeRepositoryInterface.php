<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<IndexableAttributeInterface>
 */
interface IndexableAttributeRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<array-key, IndexableAttributeInterface>
     */
    public function findEnabledByIndex(string $index): array;
}
