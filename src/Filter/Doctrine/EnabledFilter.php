<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\Config\IndexableResource;
use Sylius\Component\Resource\Model\ToggleableInterface;

final class EnabledFilter extends AbstractFilter
{
    public function apply(QueryBuilder $qb, IndexableResource $indexableResource): void
    {
        if (!$indexableResource->is(ToggleableInterface::class)) {
            return;
        }

        $rootAlias = $this->getRootAlias($qb);

        $qb->andWhere(sprintf('%s.enabled = true', $rootAlias));
    }
}
