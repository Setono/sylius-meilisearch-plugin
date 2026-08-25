<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Grid\Filter;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * This filter exists only so the "task_index_uid" filter type can be registered and its form
 * rendered. It is never applied: the tasks grid uses a data provider, which bypasses the filter
 * machinery, so the criteria are translated into a tasks query by the data provider instead.
 *
 * @see \Setono\SyliusMeilisearchPlugin\Grid\Provider\TasksDataProvider
 */
final class TaskIndexUidFilter implements FilterInterface
{
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
    }
}
