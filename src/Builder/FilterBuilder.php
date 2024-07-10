<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Builder;

use Symfony\Component\HttpFoundation\Request;

// todo move to Meilisearch namespace because it's related to the Meilisearch API
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
