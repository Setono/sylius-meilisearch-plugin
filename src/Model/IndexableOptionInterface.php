<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

/**
 * Configures a Sylius product option (referenced by its code) to be indexed in Meilisearch
 */
interface IndexableOptionInterface extends ResourceInterface, ToggleableInterface, TimestampableInterface
{
    public function getId(): ?int;

    public function getCode(): ?string;

    public function setCode(?string $code): void;

    /**
     * @return list<string>
     */
    public function getIndexes(): array;

    public function addIndex(string $index): void;

    public function removeIndex(string $index): void;

    public function hasIndex(string $index): bool;

    public function isSearchable(): bool;

    public function setSearchable(bool $searchable): void;

    public function isFilterable(): bool;

    public function setFilterable(bool $filterable): void;

    public function isFacetable(): bool;

    public function setFacetable(bool $facetable): void;

    public function getFacetPosition(): int;

    public function setFacetPosition(int $facetPosition): void;
}
