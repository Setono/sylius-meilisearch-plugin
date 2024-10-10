<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

final class Image
{
    public function __construct(
        public readonly string $name,
        public readonly string $filterSet,
        public readonly ?string $type,
    ) {
    }
}
