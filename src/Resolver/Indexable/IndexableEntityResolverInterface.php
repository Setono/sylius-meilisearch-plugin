<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\Indexable;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

/**
 * Resolves the indexable entities that are affected by a change to the given object.
 *
 * This is the extension point that lets a change to an *associated* entity (a channel price, a
 * variant's stock, a taxon assignment, …) trigger a reindex of the indexable entity that carries
 * it, rather than only reacting to changes on the indexed entity itself. Tag an implementation
 * with "setono_sylius_meilisearch.indexable_entity_resolver" (autoconfiguration adds the tag).
 */
interface IndexableEntityResolverInterface
{
    /**
     * @return iterable<IndexableInterface>
     */
    public function resolve(object $entity): iterable;
}
