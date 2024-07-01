<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Exception;

final class NonExistingResourceException extends \InvalidArgumentException
{
    /**
     * @param list<string> $availableResources
     */
    public static function fromNameAndIndex(string $name, string $index, array $availableResources = []): self
    {
        $message = sprintf('No resource exists with the name "%s" on the index "%s".', $name, $index);

        if ([] !== $availableResources) {
            $message .= sprintf(' Available resources are: [%s]', implode(', ', $availableResources));
        }

        return new self($message);
    }
}
