<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Builder;

use Symfony\Component\HttpFoundation\Request;

final class FilterBuilder implements FilterBuilderInterface
{
    public function build(Request $request): array
    {
        $filters = [];

        if ($request->query->has('onSale')) {
            $filters[] = 'onSale = true';
        }

        return $filters;
    }
}
