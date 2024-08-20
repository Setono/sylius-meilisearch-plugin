<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Builder;

use Symfony\Component\HttpFoundation\Request;

interface FilterBuilderInterface
{
    public function build(array $parameters): array;
}
