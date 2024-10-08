<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAwareTrait;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="sylius_taxon")
 */
class Taxon extends BaseTaxon implements IndexableInterface
{
    use IndexableAwareTrait;
}
