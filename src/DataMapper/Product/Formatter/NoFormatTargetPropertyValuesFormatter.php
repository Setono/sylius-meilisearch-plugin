<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Formatter;

final class NoFormatTargetPropertyValuesFormatter implements TargetPropertyValuesFormatterInterface
{
    public function format(array $values): array
    {
        return $values;
    }
}
