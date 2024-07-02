<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Message\Command;

use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Webmozart\Assert\Assert;

final class IndexEntities implements CommandInterface
{
    public function __construct(
        /** @var class-string<IndexableInterface> $class */
        public readonly string $class,
        public readonly array $ids,
    ) {
        Assert::stringNotEmpty($class);
        Assert::notEmpty($ids);
    }
}
