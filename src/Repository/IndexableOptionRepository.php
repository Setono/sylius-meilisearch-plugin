<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\IndexableOptionInterface;
use Webmozart\Assert\Assert;

class IndexableOptionRepository extends IndexableSubjectRepository implements IndexableOptionRepositoryInterface
{
    public function findEnabledByIndex(string $index): array
    {
        $objs = $this->doFindEnabledByIndex($index);

        Assert::allIsInstanceOf($objs, IndexableOptionInterface::class);

        return $objs;
    }
}
