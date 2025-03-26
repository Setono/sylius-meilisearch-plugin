<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Engine;

use Symfony\Component\HttpFoundation\Request;

final class SearchRequest
{
    public const QUERY_PARAMETER_SEARCH = 'q';

    public const QUERY_PARAMETER_PAGE = 'p';

    public const QUERY_PARAMETER_SORT = 's';

    public const QUERY_PARAMETER_FILTER = 'f';

    // todo we need the hits per page here
    public function __construct(
        public ?string $query,
        /** @var array<string, mixed> $filters */
        public array $filters = [],
        public int $page = 1,
        public ?string $sort = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $q = $request->query->get(self::QUERY_PARAMETER_SEARCH);
        if (!is_string($q)) {
            $q = null;
        }

        $page = max(1, (int) $request->query->get(self::QUERY_PARAMETER_PAGE, '1'));

        $sort = $request->query->get(self::QUERY_PARAMETER_SORT);
        if (!is_string($sort)) {
            $sort = null;
        }

        /** @var array<string, mixed> $filters */
        $filters = $request->query->all(self::QUERY_PARAMETER_FILTER);

        return new self($q, $filters, $page, $sort);
    }
}
