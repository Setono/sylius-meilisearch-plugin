<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\IndexableSubjectInterface;

interface IndexableSubjectRepositoryInterface
{
    /**
     * @return array<array-key, IndexableSubjectInterface>
     */
    public function findEnabledByIndex(string $index): array;
}
