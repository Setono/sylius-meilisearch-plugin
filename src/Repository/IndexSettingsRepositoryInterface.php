<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Model\IndexSettingsInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<IndexSettingsInterface>
 */
interface IndexSettingsRepositoryInterface extends RepositoryInterface
{
    public function findOneByIndex(string|Index $index): ?IndexSettingsInterface;
}
