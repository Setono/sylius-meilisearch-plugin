<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Setono\SyliusMeilisearchPlugin\Document\Document;

final class MetadataFactory implements MetadataFactoryInterface
{
    public function getMetadataFor(string|Document $document): MetadataInterface
    {
        return new Metadata($document);
    }
}
