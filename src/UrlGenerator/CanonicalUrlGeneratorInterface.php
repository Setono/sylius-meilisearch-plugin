<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Symfony\Component\HttpFoundation\Request;

interface CanonicalUrlGeneratorInterface
{
    public function generate(Request $request, array $parameters = []): string;
}
