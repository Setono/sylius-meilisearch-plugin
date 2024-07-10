<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface SynonymInterface extends ResourceInterface, ChannelAwareInterface
{
    public function getId(): ?int;

    public function getTerm(): ?string;

    public function setTerm(?string $term): void;

    public function getSynonym(): ?string;

    public function setSynonym(?string $synonym): void;

    public function getLocale(): ?LocaleInterface;

    public function setLocale(?LocaleInterface $locale): void;

    // todo can we think of a scenario where we _really_ need the channel?
    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): void;
}
