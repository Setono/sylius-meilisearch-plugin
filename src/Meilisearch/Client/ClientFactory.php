<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Client;

use Meilisearch\Client;
use Psr\Http\Client\ClientInterface;

final class ClientFactory implements ClientFactoryInterface
{
    public function __construct(
        private readonly string $url,
        private readonly string $apiKey,
        private readonly ClientInterface $httpClient,
    ) {
    }

    public function create(): Client
    {
        return new Client($this->url, $this->apiKey, $this->httpClient);
    }
}
