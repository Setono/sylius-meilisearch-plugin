<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAttributeInterface;
use Webmozart\Assert\Assert;

class IndexableAttributeRepository extends IndexableSubjectRepository implements IndexableAttributeRepositoryInterface
{
    public function findEnabledByIndex(string $index): array
    {
        $objs = $this->doFindEnabledByIndex($index);

        Assert::allIsInstanceOf($objs, IndexableAttributeInterface::class);

        return $objs;
    }
}
