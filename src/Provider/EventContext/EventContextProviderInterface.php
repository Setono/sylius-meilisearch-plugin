<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Provider\EventContext;

use Setono\SyliusMeilisearchPlugin\Client\InsightsClient\EventContext;

interface EventContextProviderInterface
{
    public function getEventContext(): EventContext;
}
