<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig;

use Setono\SyliusMeilisearchPlugin\Twig\Variable\Autocomplete;

final class Variable
{
    private Autocomplete $autocomplete;

    private string $masterKey;

    private string $searchOnlyApiKey;

    public function __construct(Autocomplete $autocomplete, string $masterKey)
    {
        $this->autocomplete = $autocomplete;
        $this->masterKey = $masterKey;
    }

    public function getAutocomplete(): Autocomplete
    {
        // todo before returning check if search is enabled in the configuration of the plugin

        return $this->autocomplete;
    }

    public function getMasterKey(): string
    {
        return $this->masterKey;
    }
}
