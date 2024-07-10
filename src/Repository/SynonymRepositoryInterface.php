<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Repository;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<SynonymInterface>
 */
interface SynonymRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<array-key, SynonymInterface>
     */
    public function findByLocaleAndChannel(string $localeCode, string $channelCode = null): array;
}
