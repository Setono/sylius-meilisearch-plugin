<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

// todo should this extend from Filterable?
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Facet
{
}
