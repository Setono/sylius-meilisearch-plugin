<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

abstract class IndexableSubjectRepository extends EntityRepository
{
    /**
     * The `indexes` column is a JSON list (e.g. ["products"]). The LIKE pattern is anchored
     * to the quoted form (`%"products"%`) on purpose: without the surrounding quotes an index
     * name that is a prefix of another (`products` vs `products_v2`) would false-positive.
     * See SynonymRepository for the same pattern
     *
     * @return list<object>
     */
    protected function doFindEnabledByIndex(string $index): array
    {
        $objs = $this->createQueryBuilder('o')
            ->andWhere('o.enabled = true')
            ->andWhere('o.indexes LIKE :index')
            ->setParameter('index', '%"' . $index . '"%')
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($objs);
        Assert::allObject($objs);

        return array_values($objs);
    }
}
