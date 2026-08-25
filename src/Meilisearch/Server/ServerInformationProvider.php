<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Server;

use Meilisearch\Client;
use Psr\Log\LoggerInterface;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;

final class ServerInformationProvider implements ServerInformationProviderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly IndexUidsProviderInterface $indexUidsProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(): ServerInformation
    {
        $healthy = $this->client->isHealthy();

        $version = null;

        try {
            $pkgVersion = $this->client->version()['pkgVersion'] ?? null;
            $version = is_string($pkgVersion) ? $pkgVersion : null;
        } catch (\Throwable) {
        }

        $lastUpdate = null;
        $statsAvailable = false;

        /** @var array<string, mixed> $indexStats */
        $indexStats = [];

        try {
            $stats = $this->client->stats();
            $statsAvailable = true;

            $lastUpdate = self::dateTime($stats['lastUpdate'] ?? null);

            if (isset($stats['indexes']) && is_array($stats['indexes'])) {
                $indexStats = $stats['indexes'];
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Unable to fetch stats from Meilisearch: %s', $e->getMessage()));
        }

        try {
            $uidsByIndex = $this->indexUidsProvider->getAll();
        } catch (\Throwable $e) {
            // enumerating index scopes queries the database, and the admin page should still render if that fails
            $this->logger->warning(sprintf('Unable to resolve the configured index uids: %s', $e->getMessage()));

            $uidsByIndex = [];
        }

        $indexes = [];

        foreach ($uidsByIndex as $name => $uids) {
            $indexes[$name] = [];

            foreach ($uids as $uid) {
                $statsForUid = $indexStats[$uid] ?? null;
                if (!is_array($statsForUid)) {
                    $indexes[$name][] = new IndexStatus($uid, null, null, false);

                    continue;
                }

                $numberOfDocuments = $statsForUid['numberOfDocuments'] ?? null;
                $isIndexing = $statsForUid['isIndexing'] ?? null;

                $indexes[$name][] = new IndexStatus(
                    $uid,
                    is_int($numberOfDocuments) ? $numberOfDocuments : null,
                    is_bool($isIndexing) ? $isIndexing : null,
                    true,
                );
            }
        }

        return new ServerInformation($healthy, $version, $lastUpdate, $statsAvailable, $indexes);
    }

    private static function dateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
