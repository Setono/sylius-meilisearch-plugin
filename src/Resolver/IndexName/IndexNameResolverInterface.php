<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexName;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

interface IndexNameResolverInterface
{
    /**
     * todo if the entity is present on two indexes, we can't resolve the index name. What to do?
     *
     * Will resolve an index name from the current application context, i.e. channel context, locale context etc
     *
     * @param class-string<IndexableInterface>|Index $resource
     */
    public function resolve($resource): string;

    public function resolveFromIndexScope(IndexScope $indexScope): string;
}
