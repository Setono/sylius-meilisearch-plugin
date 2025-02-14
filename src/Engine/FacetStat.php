<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

final class FacetStat
{
    public readonly float|int $min;

    public readonly float|int $max;

    public function __construct(
        public readonly string $name,
        array $values,
    ) {
        if (!isset($values['min'], $values['max'])) {
            throw new \InvalidArgumentException('The $values must contain a min and a max key');
        }

        if (!is_numeric($values['min']) || !is_numeric($values['max'])) {
            throw new \InvalidArgumentException('The $values must contain numeric values');
        }

        $this->min = $values['min'] + 0;
        $this->max = $values['max'] + 0;
    }
}
