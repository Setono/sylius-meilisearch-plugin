<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Image
{
    public function __construct(
        public readonly string $filterSet,
        public readonly ?string $type = null,
    ) {
    }
}
