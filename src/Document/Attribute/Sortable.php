<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Sortable
{
    final public const ASC = 'asc';

    final public const DESC = 'desc';

    public function __construct(
        /** The direction of the sorting. If null, both directions are allowed */
        public readonly ?string $direction = null,
    ) {
    }
}
