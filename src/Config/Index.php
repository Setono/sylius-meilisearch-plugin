<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Exception\NonExistingResourceException;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;

final class Index implements \Stringable
{
    public function __construct(
        /**
         * This is the name you gave the index in the configuration.
         * This name is also used when resolving the final index name in Meilisearch, so do not change this unless you know what you're doing
         */
        public readonly string $name,
        /**
         * This is the FQCN for the document that is mapped to an index in Algolia.
         * If you are indexing products this could be Setono\SyliusMeilisearchPlugin\Document\Product
         *
         * @var class-string<Document> $document
         */
        public readonly string $document,
        public readonly IndexerInterface $indexer,
        /**
         * An array of resources, indexed by the resource name
         *
         * @var array<string, IndexableResource> $resources
         */
        public readonly array $resources,
        public readonly ?string $prefix = null,
    ) {
        if (!is_a($document, Document::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The document class %s MUST be an instance of %s',
                $document,
                Document::class,
            ));
        }
    }

    public function hasResource(string|IndexableResource $resource): bool
    {
        if ($resource instanceof IndexableResource) {
            $resource = $resource->name;
        }

        return isset($this->resources[$resource]);
    }

    /**
     * @throws NonExistingResourceException if a resource with the given name doesn't exist on this index
     */
    public function getResource(string $name): IndexableResource
    {
        if (!$this->hasResource($name)) {
            throw NonExistingResourceException::fromNameAndIndex($name, $this->name, array_keys($this->resources));
        }

        return $this->resources[$name];
    }

    /**
     * Returns true if any of the resources configured on this index is an instance of the given class
     *
     * @param class-string $class
     */
    public function hasResourceWithClass(string $class): bool
    {
        foreach ($this->resources as $resource) {
            if ($resource->is($class)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
