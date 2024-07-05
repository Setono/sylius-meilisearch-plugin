<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document;

class Taxon extends Document implements UrlAwareInterface
{
    public ?string $name = null;

    public ?string $url = null;

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
