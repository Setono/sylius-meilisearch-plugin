<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Config\IndexableResource;

final class IndexEntities implements CommandInterface
{
    public IndexableResource $resource;

    /** @var non-empty-list<mixed> */
    public array $ids;

    /**
     * @param non-empty-list<mixed> $ids
     */
    public function __construct(IndexableResource $resource, array $ids)
    {
        $this->resource = $resource;
        $this->ids = $ids;
    }
}
