<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableDataFilter;

use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;
use Sylius\Component\Channel\Model\ChannelsAwareInterface;

final class ChannelsAwareFilter extends AbstractFilter
{
    public function __construct(private readonly string $index)
    {
    }

    public function filter(QueryBuilderForDataProvisionCreated $event): void
    {
        if ($event->index->name !== $this->index) {
            return;
        }

        if (is_a($event->entity, ChannelsAwareInterface::class, true)) {
            // At this point we cannot filter based on the channel because it's not available,
            // but we can filter out entities that are not assigned to any channel
            $event->qb->andWhere(sprintf('SIZE(%s.channels) > 0', self::getRootAlias($event->qb)));
        }
    }
}
