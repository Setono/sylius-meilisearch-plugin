<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Document;

interface MetadataFactoryInterface
{
    /**
     * @param class-string<Document>|Document $document
     */
    public function getMetadataFor(string|Document $document): Metadata;
}
