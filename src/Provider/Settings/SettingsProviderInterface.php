<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\Settings;

use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;

interface SettingsProviderInterface
{
    public function getSettings(IndexScope $indexScope): Settings;
}
