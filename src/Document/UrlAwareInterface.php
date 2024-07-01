<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

interface UrlAwareInterface
{
    /**
     * Sets the URL on the respective document
     */
    public function setUrl(string $url): void;
}
