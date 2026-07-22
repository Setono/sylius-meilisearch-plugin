<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Factory\IndexSettingsFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Repository\IndexSettingsRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EnsureIndexSettingsAreCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly IndexSettingsRepositoryInterface $indexSettingsRepository,
        private readonly IndexSettingsFactoryInterface $indexSettingsFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_meilisearch.index_settings.index' => 'ensure',
        ];
    }

    public function ensure(): void
    {
        foreach ($this->indexRegistry->getNames() as $name) {
            $indexSettings = $this->indexSettingsRepository->findOneByIndex($name);
            if (null !== $indexSettings) {
                continue;
            }

            $indexSettings = $this->indexSettingsFactory->createWithIndexName($name);
            $this->indexSettingsRepository->add($indexSettings);
        }
    }
}
