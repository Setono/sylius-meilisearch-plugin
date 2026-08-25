<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Server;

interface ServerInformationProviderInterface
{
    /**
     * Never throws: when the Meilisearch server (or the database) is unreachable, the returned
     * object simply carries less information
     */
    public function get(): ServerInformation;
}
