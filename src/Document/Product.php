<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;

class Product extends Document implements UrlAwareInterface, ImageAwareInterface
{
    #[Searchable]
    public ?string $name = null;

    #[Sortable(direction: Sortable::DESC)]
    public ?int $createdAt = null;

    public ?string $url = null;

    public ?string $image = null;

    /**
     * Holds a list of taxon codes. This makes it easy to filter by a taxon.
     *
     * @var list<string>
     */
    #[Filterable]
    public array $taxonCodes = [];

    public ?string $currency = null;

    #[Facet]
    #[Sortable(direction: Sortable::ASC)]
    public ?float $price = null;

    public ?float $originalPrice = null;

    /**
     * This attribute will allow you to create a filter like 'Only show products on sale'
     */
    #[Facet]
    public function isOnSale(): bool
    {
        return null !== $this->originalPrice && null !== $this->price && $this->price < $this->originalPrice;
    }

    #[Sortable(direction: Sortable::DESC)]
    public function getDiscount(): float
    {
        if (null === $this->originalPrice || null === $this->price) {
            return 0;
        }

        return max(0, $this->originalPrice - $this->price);
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setImage(string $image): void
    {
        $this->image = $image;
    }
}
