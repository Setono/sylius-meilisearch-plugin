<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SearchExtension extends AbstractExtension
{
    /**
     * @param bool $enabled Whether the search functionality is enabled or not
     */
    public function __construct(private readonly bool $enabled)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ssm_search_enabled', $this->isEnabled(...)),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
