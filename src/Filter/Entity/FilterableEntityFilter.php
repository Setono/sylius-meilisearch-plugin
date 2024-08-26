<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Entity;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\FilterableInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

final class FilterableEntityFilter implements EntityFilterInterface
{
    public function filter(IndexableInterface $entity, Document $document, IndexScope $indexScope): bool
    {
        return $entity instanceof FilterableInterface ? $entity->filter($indexScope) : true;
    }
}
