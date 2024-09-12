<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\DataMapper\Product\Formatter;

use PHPUnit\Framework\TestCase;
use Setono\SyliusMeilisearchPlugin\DataMapper\Product\Formatter\NoFormatTargetPropertyValuesFormatter;

final class NoFormatTargetPropertyValuesFormatterTest extends TestCase
{
    public function testItDoesNotFormatValues(): void
    {
        $formatter = new NoFormatTargetPropertyValuesFormatter();

        self::assertSame(['value1', 'value2', 'value3'], $formatter->format(['value1', 'value2', 'value3']));
        self::assertSame([['value1', 'value2', 'value3']], $formatter->format([['value1', 'value2', 'value3']]));
        self::assertSame([['value1'], ['value2'], ['value3']], $formatter->format([['value1'], ['value2'], ['value3']]));
    }
}
