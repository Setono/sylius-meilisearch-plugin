<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Stubs\Entity;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAwareTrait;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;

class Taxon extends BaseTaxon implements IndexableInterface
{
    use IndexableAwareTrait;
}
