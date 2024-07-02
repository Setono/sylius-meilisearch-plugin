<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractEntityUrlGenerator implements EntityUrlGeneratorInterface
{
    public function __construct(protected UrlGeneratorInterface $urlGenerator)
    {
    }
}
