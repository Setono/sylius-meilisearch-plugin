<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableDataFilter;

use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;
use Sylius\Component\Resource\Model\ToggleableInterface;

final class EnabledFilter extends AbstractFilter
{
    public function __construct(private readonly string $index)
    {
    }

    public function filter(QueryBuilderForDataProvisionCreated $event): void
    {
        if ($event->index->name !== $this->index) {
            return;
        }

        if (is_a($event->entity, ToggleableInterface::class, true)) {
            $event->qb->andWhere(sprintf('%s.enabled = true', self::getRootAlias($event->qb)));
        }
    }
}
