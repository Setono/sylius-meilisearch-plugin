<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable as SortableAttribute;

final class Sortable
{
    public function __construct(
        public readonly string $name,
        /**
         * The direction of the sorting. If null, both directions are allowed
         */
        public readonly ?string $direction = null,
    ) {
    }

    /**
     * The directions this attribute may be sorted in, respecting any restriction
     * declared via #[Sortable(direction:)].
     *
     * @return list<string>
     */
    public function directions(): array
    {
        if (null === $this->direction) {
            return [SortableAttribute::ASC, SortableAttribute::DESC];
        }

        return [$this->direction];
    }
}
