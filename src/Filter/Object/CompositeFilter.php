<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Filter\Object;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Sylius\Component\Resource\Model\ResourceInterface;

final class CompositeFilter implements FilterInterface
{
    /** @var list<FilterInterface> */
    private array $filters = [];

    public function add(FilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    public function filter(ResourceInterface $entity, Document $document, IndexScope $indexScope): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->filter($entity, $document, $indexScope)) {
                return false;
            }
        }

        return true;
    }
}
