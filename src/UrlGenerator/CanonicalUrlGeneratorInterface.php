<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Symfony\Component\HttpFoundation\Request;

interface CanonicalUrlGeneratorInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(Request $request, array $parameters = []): string;
}
