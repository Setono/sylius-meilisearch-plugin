<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;
use Webmozart\Assert\Assert;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Sortable
{
    final public const ASC = 'asc';

    final public const DESC = 'desc';

    public function __construct(
        /** The direction of the sorting. If null, both directions are allowed */
        public readonly ?string $direction = null,
    ) {
        if (null !== $direction) {
            Assert::oneOf(
                $direction,
                [self::ASC, self::DESC],
                sprintf('The #[Sortable] direction must be one of "%s" or "%s" (or null for both), got %%s', self::ASC, self::DESC),
            );
        }
    }
}
