<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration;

final class Source
{
    public function __construct(
        public readonly string $id,
        public readonly string $index,
        /** The attribute that holds the URL on any given item/document */
        public readonly ?string $urlAttribute = null,
    ) {
    }
}
