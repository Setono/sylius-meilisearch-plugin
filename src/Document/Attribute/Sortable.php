<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Sortable
{
    final public const ASC = 'asc';

    final public const DESC = 'desc';

    final public const BOTH = 'both';

    public function __construct(public readonly string $direction = self::BOTH)
    {
    }
}
