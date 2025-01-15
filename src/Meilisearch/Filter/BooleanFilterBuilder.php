<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Meilisearch\Filter;

final class BooleanFilterBuilder implements FilterBuilderInterface
{
    public function build(array $facets, array $facetsValues): array
    {
        $filters = [];

        foreach ($facets as $facet) {
            if ($facet->type === 'bool' && isset($facetsValues[$facet->name])) {
                $value = self::isTruthy($facetsValues[$facet->name]) ? 'true' : 'false';
                $filters[] = sprintf('%s = %s', $facet->name, $value);
            }
        }

        return $filters;
    }

    private static function isTruthy(mixed $value): bool
    {
        if (is_string($value)) {
            return '1' === $value || 'true' === $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return 1 === $value;
        }

        return false;
    }
}
