<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event\Search;

use Symfony\Component\HttpFoundation\Request;

final class SearchResponseParametersCreated
{
    public function __construct(
        /**
         * @var non-empty-string
         *
         * The Twig template to render
         */
        public string $template,

        /**
         * @var array<string, mixed>
         *
         * The context to render the Twig template with
         */
        public array $context,
        public readonly Request $request,
    ) {
    }
}
