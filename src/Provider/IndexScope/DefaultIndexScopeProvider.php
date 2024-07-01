<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\IndexScope\IndexScope;

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

    public function getFromChannelAndLocaleAndCurrency(
        Index $index,
        string $channelCode = null,
        string $localeCode = null,
        string $currencyCode = null
    ): IndexScope {
        return $this->getFromContext($index);
    }

    public function supports(Index $index): bool
    {
        return true;
    }
}
