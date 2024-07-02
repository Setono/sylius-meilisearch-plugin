<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

interface IndexableInterface
{
    public function getId();

    /**
     * This will be the document id in Meilisearch. This MUST be unique across the index, therefore if you mix
     * products and taxons in an index for example, use a prefix
     */
    public function getDocumentIdentifier(): ?string;
}
