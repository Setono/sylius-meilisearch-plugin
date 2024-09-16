<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
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

    #[Facet]
    #[MapProductOption(['t_shirt_size', 'dress_size'])]
    public ?string $size = null;

    #[Facet]
    #[Sortable]
    public ?int $price = null;

    #[Filterable]
    public array $taxons = [];

    /** @var list<string> */
    #[Facet]
    #[MapProductAttribute(['t_shirt_collection', 'dress_collection'])]
    public array $collection = [];

    /** @var list<string> */
    #[Facet]
    #[MapProductAttribute(['t_shirt_brand', 'dress_brand'])]
    public array $brand = [];
}
