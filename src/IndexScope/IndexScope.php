<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\IndexScope;

use Setono\SyliusMeilisearchPlugin\Config\Index;

/**
 * NOT final - this makes it easier for plugin users to override this class and provide their own scope for an index
 *
 * @immutable
 */
class IndexScope
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

    public function withChannelCode(?string $channelCode): self
    {
        return new self($this->index, $channelCode, $this->localeCode, $this->currencyCode);
    }

    public function withLocaleCode(?string $localeCode): self
    {
        return new self($this->index, $this->channelCode, $localeCode, $this->currencyCode);
    }

    public function withCurrencyCode(?string $currencyCode): self
    {
        return new self($this->index, $this->channelCode, $this->localeCode, $currencyCode);
    }
}
