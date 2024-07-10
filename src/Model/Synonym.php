<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use function Symfony\Component\String\u;

class Synonym implements SynonymInterface
{
    protected ?int $id = null;

    protected ?string $term = null;

    protected ?string $synonym = null;

    protected ?LocaleInterface $locale = null;

    protected ?ChannelInterface $channel = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(?string $term): void
    {
        $this->term = u($term)->ascii()->lower()->toString();
    }

    public function getSynonym(): ?string
    {
        return $this->synonym;
    }

    public function setSynonym(?string $synonym): void
    {
        $this->synonym = u($synonym)->ascii()->lower()->toString();
    }

    public function getLocale(): ?LocaleInterface
    {
        return $this->locale;
    }

    public function setLocale(?LocaleInterface $locale): void
    {
        $this->locale = $locale;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }
}
