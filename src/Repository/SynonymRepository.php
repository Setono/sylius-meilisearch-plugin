<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class SynonymRepository extends EntityRepository implements SynonymRepositoryInterface
{
    public function findEnabledByIndexScope(IndexScope $indexScope): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.enabled = true')
            ->andWhere('o.indexes LIKE :index')
            ->setParameter('index', '%"' . $indexScope->index->name . '"%')
        ;

        if (null !== $indexScope->localeCode) {
            $qb->join('o.locale', 'locale', 'WITH', 'locale.code = :localeCode')
                ->setParameter('localeCode', $indexScope->localeCode)
            ;
        }

        if (null !== $indexScope->channelCode) {
            $qb->join('o.channels', 'c', 'WITH', 'c.code = :channelCode')
                ->setParameter('channelCode', $indexScope->channelCode)
            ;
        }

        $objs = $qb->getQuery()->getResult();

        Assert::isArray($objs);
        Assert::allIsInstanceOf($objs, SynonymInterface::class);

        return $objs;
    }
}
