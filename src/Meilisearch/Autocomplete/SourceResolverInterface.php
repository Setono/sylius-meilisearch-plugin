<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration\Source;

interface SourceResolverInterface
{
    /**
     * Resolves the autocomplete source (index uid, url attribute and item template) for the given index.
     */
    public function resolve(Index $index): Source;
}
