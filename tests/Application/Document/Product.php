<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Document;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Document\Product as BaseProduct;

final class Product extends BaseProduct
{
    /** @var list<string> */
    #[Facet]
    #[MapProductOption(['t_shirt_size', 'dress_size', 'jeans_size'])]
    public array $size = [];

    /** @var list<string> */
    #[Facet]
    #[MapProductAttribute(['t_shirt_brand', 'cap_brand', 'dress_brand', 'jeans_brand'])]
    public array $brand = [];
}
