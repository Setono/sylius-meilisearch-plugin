<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration;

final class Configuration
{
    /** @var list<Source> */
    public array $sources = [];

    public function __construct(
        public readonly string $host,
        public readonly string $searchKey,

        /** This is the javascript selector for the HTML element that will contain the autocomplete */
        public readonly string $container,

        /** This is the placeholder text that will be displayed in the input field */
        public readonly string $placeholder,
        public readonly ?string $searchPath = null,
        public readonly ?string $searchParameter = 'q',
        public readonly bool $debug = false,
    ) {
    }
}
