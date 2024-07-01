<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Exception;

final class NonExistingIndexerException extends \InvalidArgumentException
{
    /**
     * @param list<string> $availableIndexers
     */
    public static function fromServiceId(string $id, array $availableIndexers = []): self
    {
        $message = sprintf('No indexer exists with the service id "%s".', $id);

        if ([] !== $availableIndexers) {
            $message .= sprintf(' Available indexers are: [%s]', implode(', ', $availableIndexers));
        }

        return new self($message);
    }
}
