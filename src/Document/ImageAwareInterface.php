<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

interface ImageAwareInterface
{
    /**
     * @param string $image The image URL
     */
    public function setImage(string $image): void;
}
