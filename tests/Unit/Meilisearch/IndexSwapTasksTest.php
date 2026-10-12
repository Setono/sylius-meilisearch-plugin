<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Contracts\TasksResults;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Meilisearch\IndexSwapTasks;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Task;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\IndexSwapTasks
 */
final class IndexSwapTasksTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_finds_swap_tasks_across_all_pages(): void
    {
        $client = $this->prophesize(Client::class);
        $client
            ->getTasks(Argument::that(static function (TasksQuery $query): bool {
                $array = $query->toArray();

                return 'indexSwap' === ($array['types'] ?? null) &&
                    'enqueued,succeeded' === ($array['statuses'] ?? null) &&
                    isset($array['afterEnqueuedAt']) &&
                    !isset($array['from']);
            }))
            ->willReturn(new TasksResults(['results' => [self::swapTask(9, 'a', 'b')], 'next' => 5]))
        ;
        $client
            ->getTasks(Argument::that(static fn (TasksQuery $query): bool => 5 === ($query->toArray()['from'] ?? null)))
            ->willReturn(new TasksResults(['results' => [self::swapTask(5, 'c', 'd')], 'next' => null]))
        ;

        $tasks = IndexSwapTasks::find($client->reveal(), ['enqueued', 'succeeded'], new \DateTimeImmutable());

        self::assertSame([9, 5], array_map(static fn (Task $task): int => $task->uid, $tasks));
    }

    /**
     * @test
     */
    public function it_returns_every_uid_a_swap_involves(): void
    {
        $task = Task::fromArray([
            'uid' => 1,
            'details' => ['swaps' => [
                ['indexes' => ['a', 'b'], 'rename' => false],
                ['indexes' => ['c', 'd']],
                'malformed',
            ]],
        ]);

        self::assertSame(['a', 'b', 'c', 'd'], IndexSwapTasks::uids($task));
        self::assertSame([], IndexSwapTasks::uids(Task::fromArray(['uid' => 2])));
    }

    private static function swapTask(int $uid, string $lhs, string $rhs): array
    {
        return [
            'uid' => $uid,
            'type' => 'indexSwap',
            'status' => 'succeeded',
            'details' => ['swaps' => [['indexes' => [$lhs, $rhs]]]],
        ];
    }
}
