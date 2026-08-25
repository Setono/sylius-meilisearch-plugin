<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexUids;

/**
 * Provides the concrete Meilisearch index uids for the configured indexes across all their scopes.
 *
 * Enumerating scopes queries the database (channels, locales, currencies), so any of these methods
 * may throw — callers decide whether that is fatal.
 */
interface IndexUidsProviderInterface
{
    /**
     * Returns the concrete Meilisearch index uids (across all scopes) for a single configured index
     *
     * @return list<string>
     *
     * @throws \InvalidArgumentException if no index exists with the given name
     */
    public function get(string $index): array;

    /**
     * @return array<string, list<string>> configured index name => concrete Meilisearch index uids
     */
    public function getAll(): array;

    /**
     * @return list<string> deduplicated list of all concrete Meilisearch index uids
     */
    public function getFlattened(): array;
}
