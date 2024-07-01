<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

interface ImageUrlsAwareInterface
{
    /**
     * A list of image urls
     *
     * @param list<string> $imageUrls
     */
    public function setImageUrls(array $imageUrls): void;

    /**
     * Adds a single image url to the document
     */
    public function addImageUrl(string $imageUrl): void;
}
