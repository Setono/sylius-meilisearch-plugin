<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facetable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;
use Setono\SyliusMeilisearchPlugin\Document\Document as BaseDocument;

final class Document extends BaseDocument
{
    #[Searchable]
    public ?string $name = null;

    #[Facetable]
    #[MapProductOption(['t_shirt_size', 'dress_size'])]
    public array $size = [];

    #[Facetable]
    #[Sortable]
    public ?int $price = null;

    #[Filterable]
    public array $taxons = [];

    /** @var list<string> */
    #[Facetable]
    #[MapProductAttribute(['t_shirt_collection', 'dress_collection'])]
    public array $collection = [];

    /** @var list<string> */
    #[Facetable]
    #[MapProductAttribute(['t_shirt_brand', 'dress_brand'])]
    public array $brand = [];
}
