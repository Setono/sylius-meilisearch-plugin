<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Entity;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Sylius\Component\Resource\Model\ToggleableInterface;

/**
 * The object-filter counterpart of the "enabled" query-builder subscriber, so incremental
 * (Doctrine event) indexing applies the same rule as a full reindex: a disabled entity is
 * filtered out (and, on the incremental path, removed from the index on its next save).
 */
final class EnabledEntityFilter implements EntityFilterInterface
{
    public function __construct(private readonly string $index)
    {
    }

    public function filter(IndexableInterface $entity, Document $document, IndexScope $indexScope): bool
    {
        if ($indexScope->index->name !== $this->index) {
            return true;
        }

        if (!$entity instanceof ToggleableInterface) {
            return true;
        }

        return $entity->isEnabled();
    }
}
