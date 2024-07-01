<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractResourceUrlGenerator implements ResourceUrlGeneratorInterface
{
    public function __construct(protected UrlGeneratorInterface $urlGenerator)
    {
    }
}
