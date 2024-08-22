<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface SynonymInterface extends ResourceInterface, ChannelsAwareInterface, ToggleableInterface, TimestampableInterface
{
    public function getId(): ?int;

    public function getTerm(): ?string;

    public function setTerm(?string $term): void;

    public function getSynonym(): ?string;

    public function setSynonym(?string $synonym): void;

    public function getLocale(): ?LocaleInterface;

    public function setLocale(?LocaleInterface $locale): void;

    /**
     * @return list<string>
     */
    public function getIndexes(): array;

    public function addIndex(string $index): void;

    public function removeIndex(string $index): void;

    public function hasIndex(string $index): bool;
}
