<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig;

use Twig\Environment;
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
            // @phpstan-ignore argument.type (Twig resolves the runtime-class callable at runtime)
            new TwigFunction('ssm_active_filters', [SearchRuntime::class, 'activeFilters']),
            new TwigFunction('ssm_search_enabled', $this->isEnabled(...)),
            new TwigFunction('ssm_search_widget', $this->renderWidget(...), [
                'needs_environment' => true,
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function renderWidget(Environment $twig): string
    {
        return $twig->render('@SetonoSyliusMeilisearchPlugin/search_widget/widget.html.twig');
    }
}
