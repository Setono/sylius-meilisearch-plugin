<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\DataMapper\Product\Provider\Exception;

class ProductPricesCannotBeProvidedException extends \RuntimeException
{
    public function __construct(
        string $message = 'Product prices cannot be provided',
        int $code = 0,
        \Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
