<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Document\Document;

interface MetadataFactoryInterface
{
    /**
     * Metadata is a function of both the document class AND the index it is resolved for: listeners
     * merge index specific attributes (e.g. the admin configured indexable attributes/options) into it
     *
     * @param class-string<Document>|Document $document
     */
    public function getMetadataFor(string|Document $document, Index $index): Metadata;
}
