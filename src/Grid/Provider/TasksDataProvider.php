<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Grid\Provider;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use Psr\Log\LoggerInterface;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Task;
use Sylius\Component\Grid\Data\DataProviderInterface;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;

/**
 * Feeds the admin tasks grid directly from the Meilisearch tasks API.
 *
 * Registered with the "sylius.grid_data_provider" tag, which means the grid's driver, filter
 * and sorting machinery is bypassed entirely — criteria and pagination are applied here.
 */
final class TasksDataProvider implements DataProviderInterface
{
    /**
     * The tasks API paginates with a cursor, not an offset, so the provider fetches everything up
     * to and including the requested page in a single request. Capping the page number keeps that
     * eager fetch bounded.
     */
    public const MAX_PAGE = 100;

    /**
     * A hard cap on the page size, regardless of the limits configured on the grid
     */
    public const MAX_LIMIT = 1_000;

    public function __construct(
        private readonly Client $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return Pagerfanta<Task>
     */
    public function getData(Grid $grid, Parameters $parameters): Pagerfanta
    {
        $limits = $grid->getLimits();
        $limit = self::toInt($parameters->get('limit'), self::toInt($limits[0] ?? null, 50));
        $maxLimit = [] === $limits ? self::MAX_LIMIT : self::toInt(max($limits), self::MAX_LIMIT);
        $limit = max(1, min($limit, $maxLimit, self::MAX_LIMIT));

        $page = max(1, min(self::toInt($parameters->get('page'), 1), self::MAX_PAGE));

        $query = self::createTasksQuery($parameters);
        $query->setLimit($page * $limit);

        $tasks = [];
        $total = 0;

        try {
            $results = $this->client->getTasks($query);

            $tasks = array_map(
                Task::fromArray(...),
                array_slice($results->getResults(), ($page - 1) * $limit, $limit),
            );
            $total = min($results->getTotal(), self::MAX_PAGE * $limit);
        } catch (\Throwable $e) {
            // the admin page must render even when Meilisearch is unreachable
            $this->logger->warning(sprintf('Unable to fetch tasks from Meilisearch: %s', $e->getMessage()));
        }

        $pagerfanta = new Pagerfanta(new FixedAdapter($total, $tasks));
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setAllowOutOfRangePages(true);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }

    public static function createTasksQuery(Parameters $parameters): TasksQuery
    {
        $query = new TasksQuery();

        $criteria = $parameters->get('criteria');
        if (!is_array($criteria)) {
            return $query;
        }

        $status = $criteria['status'] ?? null;
        if (is_string($status) && '' !== $status) {
            $query->setStatuses([$status]);
        }

        $type = $criteria['type'] ?? null;
        if (is_string($type) && '' !== $type) {
            $query->setTypes([$type]);
        }

        $indexUid = $criteria['indexUid'] ?? null;
        if (is_string($indexUid) && '' !== $indexUid) {
            $query->setIndexUids([$indexUid]);
        }

        return $query;
    }

    private static function toInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
