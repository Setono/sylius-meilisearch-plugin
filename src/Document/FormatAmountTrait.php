<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

// todo include a functions file instead
trait FormatAmountTrait
{
    protected static function formatAmount(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
