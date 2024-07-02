<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\UrlGenerator;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;

final class CompositeEntityUrlGenerator implements EntityUrlGeneratorInterface
{
    /** @var list<EntityUrlGeneratorInterface> */
    private array $generators = [];

    public function add(EntityUrlGeneratorInterface $resourceUrlGenerator): void
    {
        $this->generators[] = $resourceUrlGenerator;
    }

    public function generate(IndexableInterface $entity, array $context = []): string
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($entity, $context)) {
                return $generator->generate($entity, $context);
            }
        }

        throw new \RuntimeException(sprintf('No url generators supports the given resource %s', $entity::class));
    }

    public function supports(IndexableInterface $entity, array $context = []): bool
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($entity, $context)) {
                return true;
            }
        }

        return false;
    }
}
