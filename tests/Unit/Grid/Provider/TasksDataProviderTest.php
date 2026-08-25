<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Grid\Provider;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Contracts\TasksResults;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Setono\SyliusMeilisearchPlugin\Grid\Provider\TasksDataProvider;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Task;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Grid\Provider\TasksDataProvider
 */
final class TasksDataProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_maps_criteria_to_a_tasks_query(): void
    {
        $query = TasksDataProvider::createTasksQuery(new Parameters([
            'criteria' => [
                'status' => 'failed',
                'type' => 'settingsUpdate',
                'indexUid' => 'products__fashion_web__en_us__usd',
            ],
        ]));

        $array = $query->toArray();

        self::assertSame('failed', $array['statuses']);
        self::assertSame('settingsUpdate', $array['types']);
        self::assertSame('products__fashion_web__en_us__usd', $array['indexUids']);
    }

    /**
     * @test
     */
    public function it_ignores_empty_and_missing_criteria(): void
    {
        $query = TasksDataProvider::createTasksQuery(new Parameters([
            'criteria' => [
                'status' => '',
            ],
        ]));

        $array = $query->toArray();

        self::assertArrayNotHasKey('statuses', $array);
        self::assertArrayNotHasKey('types', $array);
        self::assertArrayNotHasKey('indexUids', $array);
    }

    /**
     * @test
     */
    public function it_fetches_and_slices_the_requested_page(): void
    {
        $results = array_map(
            static fn (int $i): array => ['uid' => 100 - $i, 'status' => 'succeeded', 'type' => 'documentAdditionOrUpdate'],
            range(0, 99),
        );

        $client = $this->prophesize(Client::class);
        $client
            ->getTasks(Argument::that(static fn (TasksQuery $query): bool => 100 === $query->toArray()['limit']))
            ->willReturn(new TasksResults(['results' => $results, 'total' => 250]))
        ;

        $provider = new TasksDataProvider($client->reveal(), $this->prophesize(LoggerInterface::class)->reveal());

        $pagerfanta = $provider->getData(self::createGrid(), new Parameters(['page' => 2, 'limit' => 50]));

        self::assertSame(250, $pagerfanta->getNbResults());
        self::assertSame(2, $pagerfanta->getCurrentPage());
        self::assertSame(50, $pagerfanta->getMaxPerPage());

        $tasks = iterator_to_array($pagerfanta->getCurrentPageResults());
        self::assertCount(50, $tasks);
        self::assertContainsOnlyInstancesOf(Task::class, $tasks);
        // the second page starts at the 51st result, i.e. uid 100 - 50
        self::assertSame(50, $tasks[0]->uid);
    }

    /**
     * @test
     */
    public function it_clamps_the_page_and_limit(): void
    {
        $client = $this->prophesize(Client::class);
        $client
            // limit clamps to max(limits) = 100 and page to MAX_PAGE, so the eager fetch is page * limit
            ->getTasks(Argument::that(static fn (TasksQuery $query): bool => TasksDataProvider::MAX_PAGE * 100 === $query->toArray()['limit']))
            ->willReturn(new TasksResults(['results' => [], 'total' => 1_000_000]))
        ;

        $provider = new TasksDataProvider($client->reveal(), $this->prophesize(LoggerInterface::class)->reveal());

        $pagerfanta = $provider->getData(self::createGrid(), new Parameters(['page' => 99999, 'limit' => 5000]));

        self::assertSame(TasksDataProvider::MAX_PAGE, $pagerfanta->getCurrentPage());
        self::assertSame(100, $pagerfanta->getMaxPerPage());
        // the total is capped so the pager never links to an unreachable page
        self::assertSame(TasksDataProvider::MAX_PAGE * 100, $pagerfanta->getNbResults());
    }

    /**
     * @test
     */
    public function it_returns_an_empty_page_when_the_client_throws(): void
    {
        $client = $this->prophesize(Client::class);
        $client->getTasks(Argument::type(TasksQuery::class))->willThrow(new \RuntimeException('Connection refused'));

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->warning(Argument::containingString('Connection refused'))->shouldBeCalled();

        $provider = new TasksDataProvider($client->reveal(), $logger->reveal());

        $pagerfanta = $provider->getData(self::createGrid(), new Parameters());

        self::assertSame(0, $pagerfanta->getNbResults());
        self::assertCount(0, iterator_to_array($pagerfanta->getCurrentPageResults()));
    }

    private static function createGrid(): Grid
    {
        $grid = Grid::fromCodeAndDriverConfiguration('setono_sylius_meilisearch_admin_task', 'doctrine/orm', []);
        $grid->setLimits([50, 100]);

        return $grid;
    }
}
