<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Builder;

use Symfony\Component\HttpFoundation\Request;

interface FilterBuilderInterface
{
    /**
     * Takes a Symfony request and returns a filter ready for the Meilisearch client
     */
    public function build(Request $request): array;
}
