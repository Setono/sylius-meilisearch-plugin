<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;
use Sylius\Component\Resource\Model\ResourceInterface;

interface IndexSettingsInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getIndexName(): ?string;

    public function setIndexName(Index|string|null $indexName): void;

    public function getSettings(): ?Settings;

    public function setSettings(?Settings $settings): void;
}
