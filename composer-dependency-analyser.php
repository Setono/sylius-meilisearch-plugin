<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    ->ignoreErrorsOnPackage('symfony/http-client', [\ShipMonk\ComposerDependencyAnalyser\Config\ErrorType::UNUSED_DEPENDENCY]) // We use the Symfony HTTP client to inject into the Meilisearch client
;
