<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

use Psr\Container\ContainerInterface;
use Setono\SyliusMeilisearchPlugin\DataProvider\IndexableDataProviderInterface;
use Setono\SyliusMeilisearchPlugin\Document\Document;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;
use Setono\SyliusMeilisearchPlugin\Document\Metadata\MetadataFactoryInterface;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;

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
        /**
         * A list of entities that should be indexed in this index
         *
         * @var list<class-string<IndexableInterface>> $entities
         */
        public readonly array $entities,
        private readonly ContainerInterface $locator,
        public readonly ?string $prefix = null,
    ) {
        if (!is_a($document, Document::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The document class %s MUST be an instance of %s',
                $document,
                Document::class,
            ));
        }

        foreach ($entities as $entity) {
            if (!is_a($entity, IndexableInterface::class, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'The entity class %s MUST be an instance of %s',
                    $entity,
                    IndexableInterface::class,
                ));
            }
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

    public function indexer(): IndexerInterface
    {
        return $this->locator->get(IndexerInterface::class);
    }

    public function dataProvider(): IndexableDataProviderInterface
    {
        return $this->locator->get(IndexableDataProviderInterface::class);
    }

    /**
     * Will return the index uid in Meilisearch based on the current context.
     * This means you should not call this when you're not in a request/response context
     */
    public function uid(): string
    {
        /** @var IndexUidResolverInterface $indexNameResolver */
        $indexNameResolver = $this->locator->get(IndexUidResolverInterface::class);

        return $indexNameResolver->resolve($this);
    }

    public function metadata(): Metadata
    {
        /** @var MetadataFactoryInterface $metadataFactory */
        $metadataFactory = $this->locator->get(MetadataFactoryInterface::class);

        return $metadataFactory->getMetadataFor($this->document);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
