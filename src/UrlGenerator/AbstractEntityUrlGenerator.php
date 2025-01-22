<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Symfony\Component\Routing\RouterInterface;

abstract class AbstractEntityUrlGenerator implements EntityUrlGeneratorInterface
{
    public function __construct(protected readonly RouterInterface $urlGenerator)
    {
    }
}
