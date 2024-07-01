<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Exception;

final class InvalidSyliusResourceException extends \InvalidArgumentException
{
    /**
     * @param list<string> $availableSyliusResources
     */
    public static function fromName(string $name, array $availableSyliusResources = []): self
    {
        $message = sprintf('No Sylius resource exists with the name "%s".', $name);

        if ([] !== $availableSyliusResources) {
            $message .= sprintf(' Available Sylius resources are: [%s]', implode(', ', $availableSyliusResources));
        }

        return new self($message);
    }
}
