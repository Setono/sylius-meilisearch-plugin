<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

/**
 * Implement this interface on entities that you want to filter in a simple way
 */
interface FilterableInterface
{
    /**
     * Works like array_filter. If the method returns true, the entity will be in the resulting set of entities to index
     */
    public function filter(IndexScope $indexScope): bool;
}
