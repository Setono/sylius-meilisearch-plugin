<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableDataFilter;

use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;
use Sylius\Component\Core\Model\ProductInterface;

final class StockAvailableFilter extends AbstractFilter
{
    public function __construct(private readonly string $index)
    {
    }

    public function filter(QueryBuilderForDataProvisionCreated $event): void
    {
        if ($event->index->name !== $this->index) {
            return;
        }

        if (!is_a($event->entity, ProductInterface::class, true)) {
            return;
        }

        $event->qb->join(sprintf('%s.variants', self::getRootAlias($event->qb)), 'variant')
            ->andWhere('variant.tracked = false OR ((variant.onHand - variant.onHold) > 0)')
        ;
    }
}
