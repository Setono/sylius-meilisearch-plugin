<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AutocompleteExtension extends AbstractExtension
{
    public function __construct(private readonly bool $enabled)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ssm_autocomplete_configuration', [AutocompleteRuntime::class, 'configuration'], ['needs_environment' => true]),
            new TwigFunction('ssm_autocomplete_enabled', $this->isEnabled(...)),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
