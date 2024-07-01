<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

abstract class EntityAwareCommand implements CommandInterface
{
    /** @var class-string<IndexableInterface> */
    public string $entityClass;

    /** @var mixed */
    public $entityId;

    public function __construct(IndexableInterface $resource)
    {
        $this->entityClass = $resource::class;
        $this->entityId = $resource->getId();
    }
}
