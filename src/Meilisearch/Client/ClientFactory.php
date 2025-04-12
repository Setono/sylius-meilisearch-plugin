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
        private readonly bool $debug = false,
    ) {
    }

    public function create(): Client
    {
        if ($this->debug) {
            return new TraceableClient($this->url, $this->apiKey, $this->httpClient);
        }

        return new Client($this->url, $this->apiKey, $this->httpClient);
    }
}
