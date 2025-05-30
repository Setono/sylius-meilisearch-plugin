<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Event;

use Setono\SyliusMeilisearchPlugin\Document\Metadata\Metadata;

final class MetadataCreated
{
    public function __construct(public readonly Metadata $metadata)
    {
    }
}
