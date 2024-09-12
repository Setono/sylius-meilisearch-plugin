<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product\Formatter;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Formatter\FlatArrayTargetPropertyValuesFormatter;

final class FlatArrayTargetPropertyValuesFormatterTest extends TestCase
{
    public function testItFormatValuesToFlatArray(): void
    {
        $formatter = new FlatArrayTargetPropertyValuesFormatter();

        self::assertSame(['value1', 'value2', 'value3'], $formatter->format([['value1', 'value2', 'value3']]));
        self::assertSame(['value1', 'value2', 'value3'], $formatter->format([['value1'], ['value2'], ['value3']]));
        self::assertSame(['value1', 'value2'], $formatter->format([['value1'], ['value1'], ['value2']]));
    }
}
