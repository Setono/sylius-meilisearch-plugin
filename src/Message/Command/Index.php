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

        /**
         * @deprecated the flag is ignored: a full index run now rebuilds into a temporary index and
         *             atomically swaps it with the live index, which purges stale documents without
         *             any search downtime
         */
        public readonly bool $delete = false,
    ) {
        if ($delete) {
            trigger_deprecation(
                'setono/sylius-meilisearch-plugin',
                '0.3',
                'The $delete flag on the %s message is deprecated and ignored: a full index run now rebuilds into a temporary index and atomically swaps it with the live index, which purges stale documents without any search downtime.',
                self::class,
            );
        }

        if ($index instanceof IndexConfig) {
            $index = $index->name;
        }

        $this->index = $index;
    }
}
