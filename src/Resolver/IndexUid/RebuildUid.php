<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexUid;

/**
 * A full index run rebuilds each scope into a temporary "rebuild" index, which is atomically swapped
 * with the live index once populated (see \Setono\SyliusMeilisearchPlugin\Message\Command\FinalizeIndexRebuild).
 * The rebuild uid is derived from the live uid so the two always pair up, no matter which
 * IndexUidResolverInterface implementation produced the live uid.
 */
final class RebuildUid
{
    public const SUFFIX = '__rebuild';

    private function __construct()
    {
    }

    public static function from(string $liveUid): string
    {
        return $liveUid . self::SUFFIX;
    }
}
