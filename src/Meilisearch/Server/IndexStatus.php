<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Server;

final class IndexStatus
{
    public function __construct(
        public readonly string $uid,
        public readonly ?int $numberOfDocuments,
        public readonly ?bool $isIndexing,
        /**
         * Whether the index exists on the Meilisearch server. Only meaningful when the stats
         * request succeeded, i.e. when ServerInformation::$statsAvailable is true
         */
        public readonly bool $existsOnServer,
    ) {
    }
}
