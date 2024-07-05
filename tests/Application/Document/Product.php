<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Document;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Document\Product as BaseProduct;

final class Product extends BaseProduct
{
    /** @var list<string> */
    #[Filterable]
    #[Facet]
    #[MapProductOption(['t_shirt_size', 'dress_size', 'jeans_size'])]
    public array $size = [];
}
