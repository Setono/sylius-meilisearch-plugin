<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexUid;

/**
 * A full index run rebuilds each scope into a temporary "rebuild" index, which is atomically swapped
 * with the live index once populated (see \Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild).
 *
 * Every attempt to handle an Index message gets its own rebuild id, so the rebuild uid is unique per
 * run: '{liveUid}__rebuild_{timestamp}_{requestId}_{attempt}'. This is what makes overlapping rebuilds
 * safe — two runs can never write into, swap, or delete each other's indexes. The id embeds:
 *
 * - a UTC timestamp, so an abandoned rebuild index (left behind by a crashed run) can be recognized as
 *   stale from its uid alone and deleted by a later rebuild, and so ids order runs by their start
 * - the id of the Index message it serves, so the index command can recognize the swap of the rebuild
 *   it requested
 * - a random attempt suffix, so a retried Index message never builds into the indexes of its failed
 *   predecessor (whose stray batches may still be in flight)
 *
 * Each run also has a ledger index, '{rebuild uid of its first live uid}__ledger', in which every
 * batch records itself once it has handed all its documents to Meilisearch.
 */
final class RebuildUid
{
    private const MARKER = '__rebuild';

    private const LEDGER_SUFFIX = '__ledger';

    private const ID_TIMESTAMP_FORMAT = 'YmdHis';

    private const ID_PATTERN = '/^(?<timestamp>\d{14})_(?<requestId>[0-9a-f]{6})_[0-9a-f]{4}$/';

    /**
     * A rebuild run older than this is considered abandoned: its indexes are deleted by a later
     * rebuild, and its finalization gives up. Generously longer than any realistic rebuild, so an
     * in-progress rebuild is never mistaken for a stray by an overlapping one.
     */
    public const STALE_AFTER = '-24 hours';

    private function __construct()
    {
    }

    public static function from(string $liveUid, string $rebuildId): string
    {
        return $liveUid . self::MARKER . '_' . $rebuildId;
    }

    public static function ledger(string $liveUid, string $rebuildId): string
    {
        return self::from($liveUid, $rebuildId) . self::LEDGER_SUFFIX;
    }

    /**
     * Generates the id that identifies one Index message (see RebuildUid::generateId())
     */
    public static function generateRequestId(): string
    {
        return bin2hex(random_bytes(3));
    }

    /**
     * Generates an id unique to one attempt to handle the Index message with the given request id
     */
    public static function generateId(string $requestId): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(self::ID_TIMESTAMP_FORMAT)
            . '_'
            . $requestId
            . '_'
            . bin2hex(random_bytes(2));
    }

    public static function isRebuildOf(string $uid, string $liveUid): bool
    {
        return str_starts_with($uid, $liveUid . self::MARKER);
    }

    /**
     * Returns the rebuild id embedded in a rebuild (or ledger) uid of the given live uid, or null if
     * $uid is not one. Rebuild ids order runs by their start, so they can be compared with strcmp().
     */
    public static function idOf(string $uid, string $liveUid): ?string
    {
        if (!self::isRebuildOf($uid, $liveUid)) {
            return null;
        }

        $id = ltrim(substr($uid, strlen($liveUid . self::MARKER)), '_');
        if (str_ends_with($id, self::LEDGER_SUFFIX)) {
            $id = substr($id, 0, -strlen(self::LEDGER_SUFFIX));
        }

        return $id;
    }

    /**
     * Returns the request id embedded in any rebuild (or ledger) uid, or null if $uid is not one
     */
    public static function requestIdOf(string $uid): ?string
    {
        $position = strrpos($uid, self::MARKER);
        if (false === $position) {
            return null;
        }

        $id = ltrim(substr($uid, $position + strlen(self::MARKER)), '_');
        if (str_ends_with($id, self::LEDGER_SUFFIX)) {
            $id = substr($id, 0, -strlen(self::LEDGER_SUFFIX));
        }

        if (1 !== preg_match(self::ID_PATTERN, $id, $matches)) {
            return null;
        }

        return $matches['requestId'];
    }

    public static function startedAt(string $rebuildId): ?\DateTimeImmutable
    {
        if (1 !== preg_match(self::ID_PATTERN, $rebuildId, $matches)) {
            return null;
        }

        $startedAt = \DateTimeImmutable::createFromFormat(
            '!' . self::ID_TIMESTAMP_FORMAT,
            $matches['timestamp'],
            new \DateTimeZone('UTC'),
        );

        return false === $startedAt ? null : $startedAt;
    }

    public static function isStaleId(string $rebuildId, \DateTimeImmutable $now): bool
    {
        $startedAt = self::startedAt($rebuildId);

        // An id we cannot parse cannot belong to a running rebuild (e.g. an older naming scheme)
        if (null === $startedAt) {
            return true;
        }

        return $startedAt <= $now->modify(self::STALE_AFTER);
    }

    /**
     * Whether $uid is a rebuild (or ledger) index of $liveUid that no running rebuild can still be using
     */
    public static function isStale(string $uid, string $liveUid, \DateTimeImmutable $now): bool
    {
        $id = self::idOf($uid, $liveUid);

        return null !== $id && self::isStaleId($id, $now);
    }

    public static function isLedger(string $uid): bool
    {
        return str_ends_with($uid, self::LEDGER_SUFFIX);
    }
}
