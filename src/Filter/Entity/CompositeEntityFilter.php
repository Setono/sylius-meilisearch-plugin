<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Entity;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

final class CompositeEntityFilter implements EntityFilterInterface
{
    /** @var list<EntityFilterInterface> */
    private array $filters = [];

    public function add(EntityFilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    public function filter(IndexableInterface $entity, Document $document, IndexScope $indexScope): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->filter($entity, $document, $indexScope)) {
                return false;
            }
        }

        return true;
    }
}
