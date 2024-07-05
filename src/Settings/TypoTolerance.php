<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Settings;

final class TypoTolerance
{
    public bool $enabled = true;

    /** @var array{oneTypo: int, twoTypos: int} */
    public array $minWordSizeForTypos = ['oneTypo' => 5, 'twoTypos' => 9];

    /** @var list<string> */
    public array $disableOnWords = [];

    /** @var list<string> */
    public array $disableOnAttributes = [];
}
