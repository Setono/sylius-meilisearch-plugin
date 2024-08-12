<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class EntityBasedQueryBuilderForIndexingCreatedEvent
{
    public function __construct(
        /** @var class-string<IndexableInterface> $entityClass */
        public readonly string $entityClass,
        public readonly QueryBuilder $queryBuilder,
    ) {
    }
}
