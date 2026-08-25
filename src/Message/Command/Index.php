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

    public function __construct(IndexConfig|string $index)
    {
        if ($index instanceof IndexConfig) {
            $index = $index->name;
        }

        $this->index = $index;
    }
}
