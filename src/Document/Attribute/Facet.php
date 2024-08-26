<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

// todo should this be renamed to Facetable to be consistent the other -able classes? Or does it just sound stupid?
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Facet extends Filterable
{
}
