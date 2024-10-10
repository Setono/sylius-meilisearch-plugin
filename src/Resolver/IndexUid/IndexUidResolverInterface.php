<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\IndexUid;

use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

interface IndexUidResolverInterface
{
    /**
     * Will resolve an index uid from the current application context, i.e. channel context, locale context etc
     */
    public function resolve(Index $index): string;

    public function resolveFromIndexScope(IndexScope $indexScope): string;
}
