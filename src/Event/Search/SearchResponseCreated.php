<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event\Search;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SearchResponseCreated
{
    public function __construct(
        public readonly Request $request,
        public readonly Response $response,
    ) {
    }
}
