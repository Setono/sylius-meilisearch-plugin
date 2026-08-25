<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Model;

use Sylius\Component\Product\Model\ProductOptionInterface;

class IndexableOption extends IndexableSubject implements IndexableOptionInterface
{
    protected ?ProductOptionInterface $option = null;

    public function getOption(): ?ProductOptionInterface
    {
        return $this->option;
    }

    public function setOption(?ProductOptionInterface $option): void
    {
        $this->option = $option;
    }

    public function getCode(): ?string
    {
        return $this->option?->getCode();
    }
}
