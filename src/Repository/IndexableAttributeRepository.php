<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class IndexableAttributeRepository extends EntityRepository implements IndexableAttributeRepositoryInterface
{
    public function findEnabledByIndex(string $index): array
    {
        // The `indexes` column is a JSON list (e.g. ["products"]). The LIKE pattern is anchored
        // to the quoted form (`%"products"%`) on purpose: without the surrounding quotes an index
        // name that is a prefix of another (`products` vs `products_v2`) would false-positive.
        // See SynonymRepository for the same pattern
        $objs = $this->createQueryBuilder('o')
            ->andWhere('o.enabled = true')
            ->andWhere('o.indexes LIKE :index')
            ->setParameter('index', '%"' . $index . '"%')
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($objs);
        Assert::allIsInstanceOf($objs, IndexableAttributeInterface::class);

        return $objs;
    }
}
