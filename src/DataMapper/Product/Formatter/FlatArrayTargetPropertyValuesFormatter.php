<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Formatter;

final class FlatArrayTargetPropertyValuesFormatter implements TargetPropertyValuesFormatterInterface
{
    public function format(array $values): array
    {
        return array_values(array_unique(array_merge(...$values)));
    }
}
