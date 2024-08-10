<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Document;

interface MetadataInterface
{
    /**
     * @return class-string<Document>
     */
    public function getDocument(): string;

    /**
     * @return list<Facet>
     */
    public function getFacets(): array;
}
