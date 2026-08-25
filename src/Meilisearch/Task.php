<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch;

/**
 * A single task as returned by the Meilisearch tasks API.
 *
 * The public properties are read by the grid's property access based field types.
 */
final class Task
{
    /**
     * @param array<string, mixed> $details
     * @param array{message: string|null, code: string|null, type: string|null, link: string|null}|null $error
     */
    public function __construct(
        public readonly int $uid,
        public readonly ?string $indexUid,
        public readonly string $status,
        public readonly string $type,
        public readonly ?\DateTimeImmutable $enqueuedAt,
        public readonly ?\DateTimeImmutable $startedAt,
        public readonly ?\DateTimeImmutable $finishedAt,
        public readonly ?string $duration,
        public readonly array $details,
        public readonly ?array $error,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data a raw task array as returned by the Meilisearch API
     */
    public static function fromArray(array $data): self
    {
        $error = null;
        if (isset($data['error']) && is_array($data['error'])) {
            $error = [
                'message' => self::string($data['error'], 'message'),
                'code' => self::string($data['error'], 'code'),
                'type' => self::string($data['error'], 'type'),
                'link' => self::string($data['error'], 'link'),
            ];
        }

        $details = [];
        if (isset($data['details']) && is_array($data['details'])) {
            foreach ($data['details'] as $key => $value) {
                $details[(string) $key] = $value;
            }
        }

        return new self(
            uid: is_int($data['uid'] ?? null) ? $data['uid'] : 0,
            indexUid: self::string($data, 'indexUid'),
            status: self::string($data, 'status') ?? 'unknown',
            type: self::string($data, 'type') ?? 'unknown',
            enqueuedAt: self::dateTime($data, 'enqueuedAt'),
            startedAt: self::dateTime($data, 'startedAt'),
            finishedAt: self::dateTime($data, 'finishedAt'),
            duration: self::string($data, 'duration'),
            details: $details,
            error: $error,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function dateTime(array $data, string $key): ?\DateTimeImmutable
    {
        $value = $data[$key] ?? null;
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
