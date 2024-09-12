<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Formatter;

interface TargetPropertyValuesFormatterInterface
{
    public function format(array $values): array;
}
