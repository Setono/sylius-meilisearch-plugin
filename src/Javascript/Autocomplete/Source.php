<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Javascript\Autocomplete;

use Setono\SyliusMeilisearchPlugin\Settings\Settings;

final class Source
{
    public Settings $params;

    public function __construct(public string $sourceId, public string $indexName, Settings $params = null)
    {
        $this->params = $params ?? new Settings();
    }
}
