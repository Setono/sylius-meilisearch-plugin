<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Indexer;

use Psr\Log\AbstractLogger;

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param mixed $level
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array{level: mixed, message: string, context: array<array-key, mixed>}|null
     */
    public function firstOfLevel(string $level): ?array
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                return $record;
            }
        }

        return null;
    }
}
