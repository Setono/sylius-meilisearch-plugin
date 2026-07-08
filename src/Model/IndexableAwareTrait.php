<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

trait IndexableAwareTrait
{
    public function getDocumentIdentifier(): string
    {
        return (string) $this->getId();
    }
}
