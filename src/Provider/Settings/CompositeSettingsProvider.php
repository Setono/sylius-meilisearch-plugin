<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\Settings;

use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;

/**
 * @extends CompositeService<SettingsProviderInterface>
 */
final class CompositeSettingsProvider extends CompositeService implements SettingsProviderInterface
{
    public function getSettings(IndexScope $indexScope): Settings
    {
        foreach ($this->services as $service) {
            if ($service->supports($indexScope)) {
                return $service->getSettings($indexScope);
            }
        }

        throw new \RuntimeException('No settings provider supports the given index scope');
    }

    public function supports(IndexScope $indexScope): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($indexScope)) {
                return true;
            }
        }

        return false;
    }
}
