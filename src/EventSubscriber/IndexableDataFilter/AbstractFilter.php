<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\IndexableDataFilter;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractFilter implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            QueryBuilderForDataProvisionCreated::class => 'filter',
        ];
    }

    abstract public function filter(QueryBuilderForDataProvisionCreated $event): void;

    protected static function getRootAlias(QueryBuilder $qb): string
    {
        $rootAliases = $qb->getRootAliases();

        return reset($rootAliases);
    }
}
