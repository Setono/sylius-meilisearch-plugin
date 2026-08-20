<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Document;

interface MetadataFactoryInterface
{
    /**
     * @param class-string<Document>|Document $document
     * @param Index|null $index When given, the metadata is resolved for that index specifically, which allows
     *                          listeners to add index-specific attributes (e.g. admin-configured product attributes)
     */
    public function getMetadataFor(string|Document $document, ?Index $index = null): Metadata;
}
