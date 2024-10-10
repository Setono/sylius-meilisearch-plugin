<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Symfony\Component\HttpFoundation\Request;

final class SearchRequest
{
    // todo we need the hits per page here
    public function __construct(
        public readonly ?string $query,
        /** @var array<string, mixed> $filters */
        public readonly array $filters = [],
        public readonly int $page = 1,
        public readonly ?string $sort = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $q = $request->query->get('q');
        if (!is_string($q)) {
            $q = null;
        }

        $page = max(1, (int) $request->query->get('p', 1));

        // todo rename sort to s?
        $sort = $request->query->get('sort');
        if (!is_string($sort)) {
            $sort = null;
        }

        // todo rename facets to f or filters?
        /** @var array<string, mixed> $filters */
        $filters = $request->query->all('facets');

        return new self($q, $filters, $page, $sort);
    }
}
