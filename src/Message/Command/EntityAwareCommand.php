<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

abstract class EntityAwareCommand implements CommandInterface
{
    final public function __construct(
        /** @var class-string<IndexableInterface> $class */
        public readonly string $class,
        /** @var mixed $id */
        public readonly mixed $id,
    ) {
    }

    public static function new(IndexableInterface $object): static
    {
        return new static($object::class, $object->getId());
    }
}
