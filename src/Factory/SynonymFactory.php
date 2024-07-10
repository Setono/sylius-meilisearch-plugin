<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Factory;

use Setono\SyliusMeilisearchPlugin\Model\SynonymInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class SynonymFactory implements SynonymFactoryInterface
{
    public function __construct(private readonly FactoryInterface $decorated)
    {
    }

    public function createNew(): SynonymInterface
    {
        $obj = $this->decorated->createNew();
        Assert::isInstanceOf($obj, SynonymInterface::class);

        return $obj;
    }

    public function createInverseFromExisting(SynonymInterface $synonym): SynonymInterface
    {
        $obj = $this->createNew();
        Assert::isInstanceOf($obj, SynonymInterface::class);

        $obj->setTerm($synonym->getSynonym());
        $obj->setSynonym($synonym->getTerm());
        $obj->setLocale($synonym->getLocale());
        $obj->setChannel($synonym->getChannel());

        return $obj;
    }
}
