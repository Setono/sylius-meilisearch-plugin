<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;

/**
 * NOTICE: This event is dispatched before the metadata is memoized in the factory, so a listener
 * must NEVER call MetadataFactoryInterface::getMetadataFor() - doing so recurses infinitely.
 * Mutate $metadata directly and read any other data from repositories instead
 */
final class MetadataCreated
{
    public function __construct(
        public readonly Metadata $metadata,
        /** The index the metadata is resolved for, or null when resolved without index context */
        public readonly ?Index $index = null,
    ) {
    }
}
