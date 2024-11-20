<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Autocomplete\Configuration;

final class Source
{
    public function __construct(
        public readonly string $id,
        public readonly string $index,
        /** The attribute that holds the URL on any given item/document */
        public readonly ?string $urlAttribute = null,
        /** @var array<string, string> $templates */
        public readonly array $templates = [],
    ) {
    }

    public function hasTemplates(): bool
    {
        return [] !== $this->templates;
    }

    /**
     * @psalm-assert-if-true string $this->templates[$template]
     */
    public function hasTemplate(string $template): bool
    {
        return isset($this->templates[$template]);
    }

    public function getTemplate(string $template): string
    {
        if (!$this->hasTemplate($template)) {
            throw new \InvalidArgumentException(sprintf('The template "%s" does not exist', $template));
        }

        return $this->templates[$template];
    }
}
