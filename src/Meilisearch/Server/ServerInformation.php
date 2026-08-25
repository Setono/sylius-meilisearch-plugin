<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Server;

final class ServerInformation
{
    public function __construct(
        public readonly bool $healthy,
        public readonly ?string $version,
        public readonly ?\DateTimeImmutable $lastUpdate,
        /**
         * Whether the stats request succeeded. When false, the per index statuses carry no
         * information about what exists on the server
         */
        public readonly bool $statsAvailable,
        /**
         * @var array<string, list<IndexStatus>> $indexes configured index name => status per concrete index uid
         */
        public readonly array $indexes,
    ) {
    }
}
