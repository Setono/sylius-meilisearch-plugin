<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\Settings;

use Setono\SyliusMeilisearchPlugin\Document\Product;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;

final class ProductSettingsProvider implements SettingsProviderInterface
{
    public function __construct(private readonly SettingsProviderInterface $defaultSettingsProvider)
    {
    }

    public function getSettings(IndexScope $indexScope): Settings
    {
        $settings = $this->defaultSettingsProvider->getSettings($indexScope);

        $settings->rankingRules->add('popularity:desc');

        return $settings;
    }

    public function supports(IndexScope $indexScope): bool
    {
        return is_a($indexScope->index->document, Product::class, true);
    }
}
