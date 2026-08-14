<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    protected static KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::stubAssetManifests();

        self::ensureKernelShutdown();
        self::$client = static::createClient(['environment' => 'test', 'debug' => true]);
    }

    /**
     * This suite renders full shop pages without requiring the frontend to be built (CI's integration
     * jobs never run yarn). symfony/asset 6.4.0 throws when a configured json manifest file is missing
     * (only later 6.4 patches make that respect strict_mode), so stub empty manifests when no real
     * build exists. A real build is never overwritten.
     */
    private static function stubAssetManifests(): void
    {
        foreach (['shop', 'admin'] as $build) {
            $dir = __DIR__ . '/../Application/public/build/' . $build;
            $file = $dir . '/manifest.json';

            if (is_file($file)) {
                continue;
            }

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($file, '{}');
        }
    }
}
