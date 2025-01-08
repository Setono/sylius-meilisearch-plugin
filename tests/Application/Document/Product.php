<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Document;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facetable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Document\Product as BaseProduct;
use Setono\SyliusMeilisearchPlugin\Form\Builder\Sorter\SizeSorter;

final class Product extends BaseProduct
{
    /** @var list<string> */
    #[Facetable(sorter: SizeSorter::class)]
    #[MapProductOption(['t_shirt_size', 'dress_size', 'jeans_size'])]
    public array $size = [];

    /** @var list<string> */
    #[Facetable]
    #[MapProductAttribute(['t_shirt_brand', 'cap_brand', 'dress_brand', 'jeans_brand'])]
    public array $brand = [];

    /** @var list<string> */
    #[Facetable]
    #[MapProductAttribute(['color'])]
    public array $color = [];

    #[Facetable]
    #[MapProductAttribute(['eco_friendly'])]
    public bool $ecoFriendly = false;

    #[Facetable]
    #[MapProductAttribute(['origin'])]
    public ?string $origin = null;
}
