<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Twig\Variable;

use Setono\SyliusMeilisearchPlugin\Javascript\Autocomplete\Source;
use Setono\SyliusMeilisearchPlugin\Javascript\Autocomplete\SourcesResolverInterface;

final class Autocomplete
{
    private SourcesResolverInterface $sourcesResolver;

    public function __construct(SourcesResolverInterface $sourcesResolver)
    {
        $this->sourcesResolver = $sourcesResolver;
    }

    /**
     * @return list<Source>
     */
    public function getSources(): array
    {
        return $this->sourcesResolver->getSources();
    }
}
