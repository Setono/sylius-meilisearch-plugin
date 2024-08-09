<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

use Symfony\Component\HttpFoundation\Request;

// todo this should be refactored
final class FilterBuilder implements FilterBuilderInterface
{
    public function build(Request $request): array
    {
        $filters = [];

        $query = $request->query->has('facets') ? $request->query->all('facets') : $request->query->all();

        if (isset($query['onSale'])) {
            $filters[] = 'onSale = true';
        }

        if(isset($query['brand'])) {
            foreach ($query['brand'] as $brand) {
                $filters[] = sprintf('brand = "%s"', $brand);
            }
        }

        dump($query);

        return $filters;
    }
}
