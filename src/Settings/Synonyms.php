<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Settings;

final class Synonyms implements \JsonSerializable
{
    /** @var array<string, list<string>> */
    private array $synonyms = [];

    /**
     * @param string|list<string> $synonym
     */
    public function setOneWay(string $word, string|array $synonym): void
    {
        if (is_string($synonym)) {
            $synonym = [$synonym];
        }

        $this->synonyms[$word] = $synonym;
    }

    /**
     * @param string|list<string> $synonym
     */
    public function setTwoWay(string $word, string|array $synonym): void
    {
        if (is_string($synonym)) {
            $synonym = [$synonym];
        }

        $this->setOneWay($word, $synonym);

        foreach ($synonym as $s) {
            $this->setOneWay($s, $word);
        }
    }

    public function jsonSerialize(): array
    {
        return $this->synonyms;
    }
}
