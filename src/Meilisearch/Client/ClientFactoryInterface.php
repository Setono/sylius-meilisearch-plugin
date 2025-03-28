<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Client;

use Meilisearch\Client;

interface ClientFactoryInterface
{
    public function create(): Client;
}
