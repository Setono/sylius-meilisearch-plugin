<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

interface UrlAwareInterface
{
    public function setUrl(string $url): void;
}
