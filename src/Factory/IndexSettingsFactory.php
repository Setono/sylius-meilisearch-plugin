<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Factory;

use Setono\SyliusMeilisearchPlugin\Model\IndexSettingsInterface;
use Setono\SyliusMeilisearchPlugin\Settings\Settings;
use Sylius\Resource\Factory\FactoryInterface;

final class IndexSettingsFactory implements IndexSettingsFactoryInterface
{
    public function __construct(
        /** @var FactoryInterface<IndexSettingsInterface> $decorated */
        private readonly FactoryInterface $decorated,
    ) {
    }

    public function createNew(): IndexSettingsInterface
    {
        $obj = $this->decorated->createNew();
        $obj->setSettings(new Settings());

        return $obj;
    }

    public function createWithIndexName(string $index): IndexSettingsInterface
    {
        $obj = $this->createNew();
        $obj->setIndexName($index);

        return $obj;
    }
}
