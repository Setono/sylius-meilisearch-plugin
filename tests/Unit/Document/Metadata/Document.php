<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;
use Setono\SyliusMeilisearchPlugin\Document\Document as BaseDocument;

final class Document extends BaseDocument
{
    #[Searchable]
    public ?string $name = null;

    #[Facet]
    public ?string $size = null;

    #[Facet]
    #[Sortable]
    public ?int $price = null;

    #[Filterable]
    public array $taxons = [];
}
