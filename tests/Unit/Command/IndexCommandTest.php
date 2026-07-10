<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\Command\IndexCommand;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Command\IndexCommand
 */
final class IndexCommandTest extends TestCase
{
    /**
     * @test
     */
    public function it_scopes_the_tasks_query_to_the_given_index_uids(): void
    {
        $query = IndexCommand::createTasksQuery(['products__test', 'taxons__test']);

        $array = $query->toArray();

        self::assertSame('enqueued,processing', $array['statuses']);
        self::assertSame('products__test,taxons__test', $array['indexUids']);
    }

    /**
     * @test
     */
    public function it_does_not_scope_by_index_uid_when_no_uids_are_given(): void
    {
        $query = IndexCommand::createTasksQuery([]);

        $array = $query->toArray();

        self::assertSame('enqueued,processing', $array['statuses']);
        self::assertArrayNotHasKey('indexUids', $array);
    }
}
