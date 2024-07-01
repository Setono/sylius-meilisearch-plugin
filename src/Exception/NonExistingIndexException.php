<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Exception;

final class NonExistingIndexException extends \InvalidArgumentException
{
    /**
     * @param list<string>|null $availableIndexes
     */
    public static function fromName(string $name, array $availableIndexes = []): self
    {
        $message = sprintf('No index exists with the name "%s".', $name);

        if ([] !== $availableIndexes) {
            $message .= sprintf(' Available indexes are: [%s]', implode(', ', $availableIndexes));
        }

        return new self($message);
    }
}
