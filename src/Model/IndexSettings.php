<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;

class IndexSettings implements IndexSettingsInterface
{
    protected ?int $id = null;

    protected ?string $indexName = null;

    protected ?Settings $settings = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getIndexName(): ?string
    {
        return $this->indexName;
    }

    public function setIndexName(Index|string|null $indexName): void
    {
        $this->indexName = $indexName instanceof Index ? $indexName->name : $indexName;
    }

    public function getSettings(): ?Settings
    {
        return $this->settings;
    }

    public function setSettings(?Settings $settings): void
    {
        $this->settings = $settings;
    }
}
