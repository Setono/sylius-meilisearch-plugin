<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Config\IndexableResource;

final class IndexEntities implements CommandInterface
{
    /**
     * @param non-empty-list<mixed> $ids
     */
    public function __construct(public IndexableResource $resource, public array $ids)
    {
    }
}
