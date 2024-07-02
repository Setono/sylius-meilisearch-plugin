<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntity;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

abstract class AbstractEntityHandler
{
    use ORMTrait;

    public function __construct(ManagerRegistry $managerRegistry, private readonly IndexRegistryInterface $indexRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(RemoveEntity|IndexEntity $message): void
    {
        $entity = $this->getManager($message->class)->find($message->class, $message->id);
        if (null === $entity) {
            throw new UnrecoverableMessageHandlingException(sprintf('Entity (%s) with id %s not found', $message->class, (string) $message->id));
        }

        foreach ($this->indexRegistry->getByEntity($message->class) as $index) {
            $this->execute($entity, $index);
        }
    }

    abstract protected function execute(IndexableInterface $entity, Index $index): void;
}
