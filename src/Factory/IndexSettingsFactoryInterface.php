<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Factory;

use Setono\SyliusMeilisearchPlugin\Model\IndexSettingsInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<IndexSettingsInterface>
 */
interface IndexSettingsFactoryInterface extends FactoryInterface
{
    public function createNew(): IndexSettingsInterface;

    public function createWithIndexName(string $index): IndexSettingsInterface;
}
