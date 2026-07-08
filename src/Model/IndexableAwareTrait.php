<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

// @phpstan-ignore trait.unused (the trait is used by entities in the test-app/consumer-app which are excluded from analysis)
trait IndexableAwareTrait
{
    public function getDocumentIdentifier(): string
    {
        return (string) $this->getId();
    }
}
