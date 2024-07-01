<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class Index implements \Stringable
{
    public function __construct(
        /**
         * This is the name you gave the index in the configuration.
         * The name is also used when resolving the final index name in Meilisearch, so do not change this unless you know what you're doing
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
         * A list of entities that should be indexed in this index
         *
         * @var list<class-string<IndexableInterface>> $entities
         */
        public readonly array $entities,
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

    /**
     * @param class-string|object $entity
     */
    public function hasEntity(string|object $entity): bool
    {
        if (is_object($entity)) {
            $entity = $entity::class;
        }

        foreach ($this->entities as $concreteEntity) {
            if (is_a($concreteEntity, $entity, true)) {
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
