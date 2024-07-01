<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Settings;

final class SortableReplica implements \Stringable
{
    public function __construct(public string $name, public string $attribute, public string $order)
    {
    }

    public function ranking(): string
    {
        return sprintf('%s(%s)', $this->order, $this->attribute);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
