<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facet;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;

class Product extends Document implements UrlAwareInterface, ImageUrlsAwareInterface
{
    #[Searchable]
    public ?string $name = null;

    #[Sortable(direction: Sortable::DESC)]
    public ?int $createdAt = null;

    public ?string $url = null;

    public ?string $primaryImageUrl = null;

    /**
     * All images (including the primary image url)
     *
     * @var list<string>
     */
    public array $imageUrls = [];

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

    /**
     * This attribute will allow you to create a replica index sorted by biggest discount just like this:
     *
     * public static function getSortableAttributes(): array
     * {
     *     return [
     *         'discount' => 'desc',
     *     ];
     * }
     */
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

    public function setImageUrls(array $imageUrls): void
    {
        $this->imageUrls = $imageUrls;
        $this->primaryImageUrl = null;

        if (count($imageUrls) > 0) {
            $this->primaryImageUrl = $imageUrls[0];
        }
    }

    public function addImageUrl(string $imageUrl): void
    {
        $this->imageUrls[] = $imageUrl;
        $this->primaryImageUrl = $this->imageUrls[0];
    }
}
