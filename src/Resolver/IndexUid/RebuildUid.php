<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexUid;

/**
 * A full index run rebuilds each scope into a temporary "rebuild" index, which is atomically swapped
 * with the live index once populated (see \Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild).
 *
 * Every rebuild run gets its own id, so the rebuild uid is unique per run: '{liveUid}__rebuild_{id}'.
 * This is what makes overlapping rebuilds safe — two runs can never write into, swap, or delete each
 * other's indexes. The id embeds a UTC timestamp, so an abandoned rebuild index (left behind by a
 * crashed run) can be recognized as stale from its uid alone and deleted by a later rebuild.
 */
final class RebuildUid
{
    private const MARKER = '__rebuild';

    private const ID_TIMESTAMP_FORMAT = 'YmdHis';

    /**
     * A rebuild index older than this is considered abandoned. Generously longer than any realistic
     * rebuild, so an in-progress rebuild is never mistaken for a stray by an overlapping one.
     */
    private const STALE_AFTER = '-24 hours';

    private function __construct()
    {
    }

    public static function from(string $liveUid, string $rebuildId): string
    {
        return $liveUid . self::MARKER . '_' . $rebuildId;
    }

    /**
     * Generates an id unique to one rebuild run. The random suffix separates runs started within
     * the same second.
     */
    public static function generateId(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(self::ID_TIMESTAMP_FORMAT)
            . '_'
            . bin2hex(random_bytes(3));
    }

    public static function isRebuildOf(string $uid, string $liveUid): bool
    {
        return str_starts_with($uid, $liveUid . self::MARKER);
    }

    /**
     * Whether $uid is a rebuild index of $liveUid that no running rebuild can still be using
     */
    public static function isStale(string $uid, string $liveUid, \DateTimeImmutable $now): bool
    {
        if (!self::isRebuildOf($uid, $liveUid)) {
            return false;
        }

        $id = ltrim(substr($uid, strlen($liveUid . self::MARKER)), '_');

        $timestamp = \DateTimeImmutable::createFromFormat(
            '!' . self::ID_TIMESTAMP_FORMAT,
            substr($id, 0, 14), // 'YmdHis' formats to 14 characters
            new \DateTimeZone('UTC'),
        );

        // An id we cannot parse cannot belong to a running rebuild (e.g. an older naming scheme)
        if (false === $timestamp) {
            return true;
        }

        return $timestamp <= $now->modify(self::STALE_AFTER);
    }
}
