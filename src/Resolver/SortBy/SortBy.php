<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Resolver\SortBy;

final class SortBy implements \JsonSerializable
{
    public function __construct(public string $label, public string $index)
    {
    }

    /**
     * This allows you to json_encode the result of the SortByResolver and use directly in the Algolia configuration
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->index,
        ];
    }
}
