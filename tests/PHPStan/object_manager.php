<?php

declare(strict_types=1);

use Setono\SyliusMeilisearchPlugin\Tests\Application\Kernel;

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

require __DIR__ . '/../Application/config/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();

/** @phpstan-ignore method.notFound,method.nonObject */
return $kernel->getContainer()->get('doctrine')->getManager();
