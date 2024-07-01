<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexSettings;

use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\IndexSettings;

final class IndexSettingsProvider implements IndexSettingsProviderInterface
{
    // todo implement an easier way to set settings decoupled from the document. This could be by dispatching an IndexSettingsEvent
    public function getSettings(IndexScope $indexScope): IndexSettings
    {
        return $indexScope->index->document::getDefaultSettings($indexScope);
    }
}
