<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

/**
 * This class represents a Sylius resource that is indexable
 */
final class IndexableResource implements \Stringable
{
    public function __construct(
        /**
         * This is the name of the Sylius resource, e.g. 'sylius.product'
         */
        public readonly string $name,
        /**
         * This is the FQCN for the resource
         *
         * @var class-string<IndexableInterface> $class
         */
        public readonly string $class,
    ) {
        if (!is_a($class, IndexableInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The document class %s MUST be an instance of %s',
                $class,
                IndexableInterface::class,
            ));
        }
    }

    /**
     * Returns true if the resource's class is an instance of $class
     *
     * @param object|class-string $class
     */
    public function is(object|string $class): bool
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        return is_a($this->class, $class, true);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
