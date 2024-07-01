<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Config;

use Setono\SyliusMeilisearchPlugin\Exception\NonExistingIndexException;

interface IndexRegistryInterface extends \Traversable
{
    /**
     * @throws \InvalidArgumentException if one of the resources on the $index has already been configured on another index
     */
    public function add(Index $index): void;

    /**
     * @throws NonExistingIndexException if no index exists with the given name
     */
    public function get(string $name): Index;

    /**
     * This method returns the index where the $class is configured
     *
     * @param object|class-string $class
     *
     * @throws \InvalidArgumentException if the given resource is not configured on any index
     */
    public function getByResource(object|string $class): Index;
}
