<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;

/**
 * The responsibility of the index scope provider is to provide all the relevant index scopes for a given index
 */
interface IndexScopeProviderInterface
{
    /**
     * This method will provide all the relevant index scopes for a given index
     *
     * @return iterable<IndexScope>
     */
    public function getAll(Index $index): iterable;

    /**
     * Returns an index scope from the application context (channel, locale, currency)
     */
    public function getFromContext(Index $index): IndexScope;

    public function supports(Index $index): bool;
}
