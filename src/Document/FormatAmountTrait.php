<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

trait FormatAmountTrait
{
    protected static function formatAmount(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
