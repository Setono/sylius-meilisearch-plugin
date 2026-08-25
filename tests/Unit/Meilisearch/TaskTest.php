<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Task;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\Task
 */
final class TaskTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_a_task_from_a_succeeded_task_payload(): void
    {
        $task = Task::fromArray([
            'uid' => 4,
            'indexUid' => 'products__fashion_web__en_us__usd',
            'status' => 'succeeded',
            'type' => 'documentAdditionOrUpdate',
            'details' => [
                'receivedDocuments' => 100,
                'indexedDocuments' => 100,
            ],
            'duration' => 'PT0.110724S',
            'enqueuedAt' => '2024-08-08T09:33:46.000000Z',
            'startedAt' => '2024-08-08T09:33:47.000000Z',
            'finishedAt' => '2024-08-08T09:33:47.110724Z',
        ]);

        self::assertSame(4, $task->uid);
        self::assertSame('products__fashion_web__en_us__usd', $task->indexUid);
        self::assertSame('succeeded', $task->status);
        self::assertSame('documentAdditionOrUpdate', $task->type);
        self::assertSame(['receivedDocuments' => 100, 'indexedDocuments' => 100], $task->details);
        self::assertSame('PT0.110724S', $task->duration);
        self::assertNotNull($task->enqueuedAt);
        self::assertSame('2024-08-08 09:33:46', $task->enqueuedAt->format('Y-m-d H:i:s'));
        self::assertNotNull($task->startedAt);
        self::assertNotNull($task->finishedAt);
        self::assertNull($task->error);
    }

    /**
     * @test
     */
    public function it_creates_a_task_from_a_failed_task_payload(): void
    {
        $task = Task::fromArray([
            'uid' => 7,
            'indexUid' => 'products__fashion_web__en_us__usd',
            'status' => 'failed',
            'type' => 'settingsUpdate',
            'error' => [
                'message' => 'Invalid facet distribution',
                'code' => 'invalid_search_facets',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#invalid_search_facets',
            ],
            'enqueuedAt' => '2024-08-08T09:33:46.000000Z',
        ]);

        self::assertSame('failed', $task->status);
        self::assertNotNull($task->error);
        self::assertSame('Invalid facet distribution', $task->error['message']);
        self::assertSame('invalid_search_facets', $task->error['code']);
        self::assertSame('https://docs.meilisearch.com/errors#invalid_search_facets', $task->error['link']);
    }

    /**
     * @test
     */
    public function it_handles_a_minimal_or_malformed_payload(): void
    {
        $task = Task::fromArray([
            'uid' => 'not-an-int',
            'status' => 42,
            'enqueuedAt' => 'not-a-date',
            'details' => 'not-an-array',
        ]);

        self::assertSame(0, $task->uid);
        self::assertNull($task->indexUid);
        self::assertSame('unknown', $task->status);
        self::assertSame('unknown', $task->type);
        self::assertNull($task->enqueuedAt);
        self::assertNull($task->startedAt);
        self::assertNull($task->finishedAt);
        self::assertNull($task->duration);
        self::assertSame([], $task->details);
        self::assertNull($task->error);
    }
}
