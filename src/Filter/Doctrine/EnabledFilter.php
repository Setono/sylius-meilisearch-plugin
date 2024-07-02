<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Resource\Model\ToggleableInterface;

final class EnabledFilter extends AbstractFilter
{
    public function apply(QueryBuilder $qb, string $entity): void
    {
        if (!is_a($entity, ToggleableInterface::class, true)) {
            return;
        }

        $rootAlias = $this->getRootAlias($qb);

        $qb->andWhere(sprintf('%s.enabled = true', $rootAlias));
    }
}
