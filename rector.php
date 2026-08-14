<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./.build/rector');

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/tests/Application',

        // A first-class callable to an internal PHP function keeps the function's real arity,
        // so callbacks invoked with surplus arguments (e.g. Symfony's choice_label calling with
        // ($choice, $key, $value)) fatal with ArgumentCountError, where a delegating closure
        // absorbs the extra arguments. This rule caused https://github.com/Setono/sylius-meilisearch-plugin/issues/181
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81
    ]);
};
