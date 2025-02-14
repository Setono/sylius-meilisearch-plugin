<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Model\IndexSettingsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class IndexSettingsRepository extends EntityRepository implements IndexSettingsRepositoryInterface
{
    public function findOneByIndex(string|Index $index): ?IndexSettingsInterface
    {
        $index = $index instanceof Index ? $index->name : $index;

        $obj = $this->findOneBy(['index' => $index]);
        Assert::nullOrIsInstanceOf($obj, IndexSettingsInterface::class);

        return $obj;
    }
}
