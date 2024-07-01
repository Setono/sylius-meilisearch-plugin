<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexSettings;

use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\IndexSettings;

interface IndexSettingsProviderInterface
{
    public function getSettings(IndexScope $indexScope): IndexSettings;
}
