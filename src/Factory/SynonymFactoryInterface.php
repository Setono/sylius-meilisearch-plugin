<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Factory;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<SynonymInterface>
 */
interface SynonymFactoryInterface extends FactoryInterface
{
    public function createNew(): SynonymInterface;

    public function createInverseFromExisting(SynonymInterface $synonym): SynonymInterface;
}
