<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Meilisearch\Server;

use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Setono\SyliusMeilisearchPlugin\Meilisearch\Server\ServerInformationProvider;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Meilisearch\Server\ServerInformationProvider
 */
final class ServerInformationProviderTest extends TestCase
{
    use ProphecyTrait;

    private const UID = 'products__fashion_web__en_us__usd';

    private const MISSING_UID = 'products__fashion_web__da_dk__dkk';

    /**
     * @test
     */
    public function it_provides_server_information(): void
    {
        $client = $this->prophesize(Client::class);
        $client->isHealthy()->willReturn(true);
        $client->version()->willReturn(['pkgVersion' => '1.16.0']);
        $client->stats()->willReturn([
            'databaseSize' => 123,
            'lastUpdate' => '2024-08-08T09:33:47.000000Z',
            'indexes' => [
                self::UID => [
                    'numberOfDocuments' => 42,
                    'isIndexing' => false,
                ],
            ],
        ]);

        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->getAll()->willReturn(['products' => [self::UID, self::MISSING_UID]]);

        $information = (new ServerInformationProvider($client->reveal(), $indexUidsProvider->reveal(), new NullLogger()))->get();

        self::assertTrue($information->healthy);
        self::assertSame('1.16.0', $information->version);
        self::assertNotNull($information->lastUpdate);
        self::assertTrue($information->statsAvailable);

        self::assertArrayHasKey('products', $information->indexes);
        [$first, $second] = $information->indexes['products'];

        self::assertSame(self::UID, $first->uid);
        self::assertTrue($first->existsOnServer);
        self::assertSame(42, $first->numberOfDocuments);
        self::assertFalse($first->isIndexing);

        self::assertSame(self::MISSING_UID, $second->uid);
        self::assertFalse($second->existsOnServer);
        self::assertNull($second->numberOfDocuments);
        self::assertNull($second->isIndexing);
    }

    /**
     * @test
     */
    public function it_never_throws_when_the_server_and_database_are_unreachable(): void
    {
        $client = $this->prophesize(Client::class);
        $client->isHealthy()->willReturn(false);
        $client->version()->willThrow(new \RuntimeException('Connection refused'));
        $client->stats()->willThrow(new \RuntimeException('Connection refused'));

        $indexUidsProvider = $this->prophesize(IndexUidsProviderInterface::class);
        $indexUidsProvider->getAll()->willThrow(new \RuntimeException('Database is down'));

        $information = (new ServerInformationProvider($client->reveal(), $indexUidsProvider->reveal(), new NullLogger()))->get();

        self::assertFalse($information->healthy);
        self::assertNull($information->version);
        self::assertNull($information->lastUpdate);
        self::assertFalse($information->statsAvailable);
        self::assertSame([], $information->indexes);
    }
}
