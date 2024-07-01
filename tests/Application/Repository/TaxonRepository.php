<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Application\Repository;

use Setono\SyliusMeilisearchPlugin\Repository\IndexableResourceRepositoryInterface;
use Setono\SyliusMeilisearchPlugin\Repository\TaxonRepositoryTrait;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository as BaseTaxonRepository;

class TaxonRepository extends BaseTaxonRepository implements IndexableResourceRepositoryInterface
{
    use TaxonRepositoryTrait;
}
