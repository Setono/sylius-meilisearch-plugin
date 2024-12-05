<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facetable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Image;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;

class Product extends Document implements UrlAwareInterface
{
    #[Searchable]
    public ?string $name = null;

    #[Sortable(direction: Sortable::DESC)]
    public ?int $createdAt = null;

    public ?string $url = null;

    #[Image(filterSet: 'sylius_shop_product_thumbnail')]
    public ?string $image = null;

    /**
     * Holds a list of taxon codes. This makes it easy to filter by a taxon.
     *
     * @var list<string>
     */
    #[Filterable]
    public array $taxonCodes = [];

    /** @var list<string> */
    #[Facetable]
    public array $taxons = [];

    public ?string $currency = null;

    #[Facetable]
    #[Sortable(direction: Sortable::ASC)]
    public ?float $price = null;

    public ?float $originalPrice = null;

    #[Sortable(direction: Sortable::DESC)]
    public float $popularity = 0.0;

    /**
     * This attribute will allow you to create a filter like 'Only show products on sale'
     */
    #[Facetable]
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
}
