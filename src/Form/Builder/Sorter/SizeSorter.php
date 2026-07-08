<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter;

use DragonCode\SizeSorter\Sorter;
use Webmozart\Assert\Assert;

final class SizeSorter implements FilterValuesSorterInterface
{
    /**
     * @param array<array-key, string> $values
     *
     * @return array<array-key, string>
     */
    public function sort(array $values): array
    {
        $sorted = Sorter::sort($values)->toArray();
        Assert::allString($sorted);

        return $sorted;
    }
}
