<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\Indexable;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

/**
 * The base resolver: an indexable entity resolves to itself.
 */
final class IndexableEntityResolver implements IndexableEntityResolverInterface
{
    public function resolve(object $entity): iterable
    {
        if ($entity instanceof IndexableInterface) {
            yield $entity;
        }
    }
}
