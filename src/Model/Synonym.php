<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;
use function Symfony\Component\String\u;

class Synonym implements SynonymInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $term = null;

    protected ?string $synonym = null;

    protected ?LocaleInterface $locale = null;

    /** @var Collection<array-key, ChannelInterface> */
    protected Collection $channels;

    /** @var list<string>|null */
    protected ?array $indexes = null;

    public function __construct()
    {
        $this->channels = new ArrayCollection();
    }

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

    public function getChannels(): Collection
    {
        return $this->channels;
    }

    public function addChannel(ChannelInterface $channel): void
    {
        if (!$this->hasChannel($channel)) {
            $this->channels->add($channel);
        }
    }

    public function removeChannel(ChannelInterface $channel): void
    {
        if ($this->hasChannel($channel)) {
            $this->channels->removeElement($channel);
        }
    }

    public function hasChannel(ChannelInterface $channel): bool
    {
        return $this->channels->contains($channel);
    }

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
}
