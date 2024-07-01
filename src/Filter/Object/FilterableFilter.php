<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Object;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Model\FilterableInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

final class FilterableFilter implements FilterInterface
{
    public function filter(ResourceInterface $entity, Document $document, IndexScope $indexScope): bool
    {
        return $entity instanceof FilterableInterface ? $entity->filter($indexScope) : true;
    }
}
