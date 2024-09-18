<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Setter;

use Setono\SyliusMeilisearchPlugin\Document\Document;

interface DocumentPropertyValuesSetterInterface
{
    public function setFor(Document $target, array $attributes): void;
}
