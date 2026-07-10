<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Webmozart\Assert\Assert;

final class IndexEntityHandler
{
    use ORMTrait;

    public function __construct(ManagerRegistry $managerRegistry, private readonly IndexRegistryInterface $indexRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(IndexEntity $message): void
    {
        $entity = $this->getManager($message->class)->find($message->class, $message->id);
        if (null === $entity) {
            $id = $message->id;
            Assert::scalar($id);

            throw new UnrecoverableMessageHandlingException(sprintf('Entity (%s) with id %s not found', $message->class, (string) $id));
        }

        foreach ($this->indexRegistry->getByEntity($message->class) as $index) {
            $index->indexer()->indexEntity($entity);
        }
    }
}
