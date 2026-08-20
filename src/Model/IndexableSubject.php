<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;

/**
 * The shared implementation of the admin managed "index this product attribute/option" configuration.
 * The concrete resources are IndexableAttribute and IndexableOption
 */
abstract class IndexableSubject
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    /** @var list<string>|null */
    protected ?array $indexes = null;

    protected bool $searchable = false;

    protected bool $filterable = false;

    protected bool $facetable = false;

    protected int $facetPosition = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return list<string>
     */
    public function getIndexes(): array
    {
        return $this->indexes ?? [];
    }

    public function addIndex(string $index): void
    {
        $this->indexes[] = $index;
    }

    public function removeIndex(string $index): void
    {
        $indexes = $this->getIndexes();
        $key = array_search($index, $indexes, true);
        if ($key !== false) {
            unset($indexes[$key]);
        }

        $this->indexes = [] === $indexes ? null : array_values($indexes);
    }

    public function hasIndex(string $index): bool
    {
        return in_array($index, $this->getIndexes(), true);
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable): void
    {
        $this->searchable = $searchable;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function setFilterable(bool $filterable): void
    {
        $this->filterable = $filterable;
    }

    public function isFacetable(): bool
    {
        return $this->facetable;
    }

    public function setFacetable(bool $facetable): void
    {
        $this->facetable = $facetable;
    }

    public function getFacetPosition(): int
    {
        return $this->facetPosition;
    }

    public function setFacetPosition(int $facetPosition): void
    {
        $this->facetPosition = $facetPosition;
    }
}
