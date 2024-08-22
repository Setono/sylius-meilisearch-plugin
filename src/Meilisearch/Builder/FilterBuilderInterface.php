<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

interface FilterBuilderInterface
{
    public function build(array $parameters): array;
}
