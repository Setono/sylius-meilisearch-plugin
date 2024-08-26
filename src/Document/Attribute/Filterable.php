<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class Filterable
{
}
