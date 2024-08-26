<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;

// todo I think the default scope should be the same as the taxon scope (i.e. based on channel and locale)
final class DefaultIndexScopeProvider implements IndexScopeProviderInterface
{
    public function getAll(Index $index): iterable
    {
        yield new IndexScope($index);
    }

    public function getFromContext(Index $index): IndexScope
    {
        return new IndexScope($index);
    }

    public function supports(Index $index): bool
    {
        return true;
    }
}
