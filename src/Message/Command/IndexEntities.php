<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Webmozart\Assert\Assert;

final class IndexEntities implements CommandInterface
{
    private function __construct(
        /** @var class-string<IndexableInterface> $class */
        public readonly string $class,
        /** @var list<mixed> $ids */
        public readonly array $ids,
        /**
         * The name of a configured index to constrain the indexing to. When null, the entities are
         * indexed on every index configured for the entity class.
         */
        public readonly ?string $index = null,
        /**
         * When true, the documents are written to the rebuild index of each scope (see RebuildUid)
         * instead of the live index
         */
        public readonly bool $rebuild = false,
    ) {
        Assert::stringNotEmpty($class);
        Assert::notEmpty($ids);

        if ($rebuild) {
            Assert::notNull($index, 'A rebuild batch must be constrained to the index being rebuilt');
        }
    }

    /**
     * @param list<IndexableInterface> $entities
     */
    public static function fromEntities(array $entities): self
    {
        $type = null;
        $ids = [];
        foreach ($entities as $entity) {
            if (null === $type) {
                $type = $entity::class;
            }

            Assert::same($type, $entity::class, 'All entities must be of the same type');

            $ids[] = $entity->getId();
        }

        Assert::notNull($type);

        return new self($type, $ids);
    }

    /**
     * @param class-string<IndexableInterface> $class
     * @param list<mixed> $ids
     */
    public static function fromIds(string $class, array $ids, ?string $index = null, bool $rebuild = false): self
    {
        return new self($class, $ids, $index, $rebuild);
    }
}
