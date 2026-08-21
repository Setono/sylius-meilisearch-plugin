<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Document\Metadata;

use Webmozart\Assert\Assert;

/**
 * A document field that does not exist as a declared property on the document class, but is added at
 * runtime from admin configuration (see the IndexableAttribute/IndexableOption resources). The values
 * are collected in the document's $dynamicFields bag and flattened to top level fields when the
 * document is normalized
 */
final class DynamicField
{
    public const SOURCE_ATTRIBUTE = 'attribute';

    public const SOURCE_OPTION = 'option';

    /**
     * @param string $name The field name in Meilisearch, e.g. "attr_color" or "opt_t_shirt_size"
     * @param self::SOURCE_* $source
     * @param string $code The code of the Sylius product attribute or product option
     * @param 'array'|'bool'|'float'|'string' $type The value type of the field. Also used as the Facet type
     */
    public function __construct(
        public readonly string $name,
        public readonly string $source,
        public readonly string $code,
        public readonly string $type,
    ) {
        Assert::oneOf($source, [self::SOURCE_ATTRIBUTE, self::SOURCE_OPTION]);
        Assert::oneOf($type, ['array', 'bool', 'float', 'string']);
    }
}
