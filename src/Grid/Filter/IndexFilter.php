<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Grid\Filter;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

class IndexFilter implements FilterInterface
{
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (!is_string($data) || '' === $data) {
            return;
        }

        $dataSource->restrict($dataSource->getExpressionBuilder()->like($name, '%"' . $data . '"%'));
    }
}
