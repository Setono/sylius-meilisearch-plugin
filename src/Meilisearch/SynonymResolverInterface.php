<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch;

use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;

interface SynonymResolverInterface
{
    /**
     * Will resolve synonyms for the given index scope and return an output ready for the Meilisearch API
     *
     * @return array<non-empty-string, list<non-empty-string>>
     */
    public function resolve(IndexScope $indexScope): array;
}
