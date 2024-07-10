<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class SynonymRepository extends EntityRepository implements SynonymRepositoryInterface
{
    public function findByLocaleAndChannel(string $localeCode, string $channelCode = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->join('o.locale', 'locale', 'WITH', 'locale.code = :localeCode')
            ->setParameter('localeCode', $localeCode)
        ;

        if (null !== $channelCode) {
            $qb->leftJoin('o.channel', 'c')
                ->andWhere('o.channel IS NULL OR c.code = :channelCode')
                ->setParameter('channelCode', $channelCode)
            ;
        }

        $objs = $qb->getQuery()->getResult();

        Assert::isArray($objs);
        Assert::allIsInstanceOf($objs, SynonymInterface::class);

        return $objs;
    }
}
