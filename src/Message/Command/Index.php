<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Config\Index as IndexConfig;

final class Index implements CommandInterface
{
    /**
     * This is the index to be indexed
     */
    public readonly string $index;

    public function __construct(
        IndexConfig|string $index,

        /** If this is true, the index will be deleted before it is created and populated */
        public readonly bool $delete = false,
    ) {
        if ($index instanceof IndexConfig) {
            $index = $index->name;
        }

        $this->index = $index;
    }
}
