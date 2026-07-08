<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin;

if (!\function_exists(__NAMESPACE__ . '\formatAmount')) {
    function formatAmount(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
