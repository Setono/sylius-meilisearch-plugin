<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;

final class IndexScope
{
    public function __construct(
        /**
         * The index that this scope applies to
         */
        public readonly Index $index,
        public readonly ?string $channelCode = null,
        public readonly ?string $localeCode = null,
        public readonly ?string $currencyCode = null,
    ) {
    }
}
