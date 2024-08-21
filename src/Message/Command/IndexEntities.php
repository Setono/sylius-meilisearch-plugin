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
    ) {
        Assert::stringNotEmpty($class);
        Assert::notEmpty($ids);
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
    public static function fromIds(string $class, array $ids): self
    {
        return new self($class, $ids);
    }
}
