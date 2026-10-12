<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Config\Index as IndexConfig;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;

final class Index implements CommandInterface
{
    /**
     * This is the index to be indexed
     */
    public readonly string $index;

    /**
     * Identifies this request. The rebuild id of every attempt to handle it embeds this id, which is
     * how the index command recognizes the swap of the rebuild it requested (see RebuildUid).
     */
    public readonly string $requestId;

    public function __construct(IndexConfig|string $index)
    {
        if ($index instanceof IndexConfig) {
            $index = $index->name;
        }

        $this->index = $index;
        $this->requestId = RebuildUid::generateRequestId();
    }
}
