<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class QueryBuilderForDataProvisionCreated
{
    public function __construct(
        /** @var class-string<IndexableInterface> $entity */
        public readonly string $entity,
        public readonly Index $index,
        public readonly QueryBuilder $qb,
    ) {
    }
}
