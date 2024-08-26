<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

final class Filterable
{
    public function __construct(public readonly string $name)
    {
    }
}
