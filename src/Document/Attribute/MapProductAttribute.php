<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class MapProductAttribute
{
    /** @var list<string> */
    public readonly array $codes;

    /**
     * todo should be nullable to just use the property name as the code
     *
     * @param list<string>|string $codes
     */
    public function __construct(array|string $codes)
    {
        $this->codes = is_string($codes) ? [$codes] : $codes;
    }
}
