<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use Meilisearch\Client;
use Meilisearch\Contracts\IndexesQuery;
use Meilisearch\Contracts\IndexesResults;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Meilisearch\SynonymResolverInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\UpdateSynonyms;
use Setono\SyliusMeilisearchPlugin\Message\Handler\UpdateSynonymsHandler;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Message\Handler\UpdateSynonymsHandler
 */
final class UpdateSynonymsHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_pushes_the_synonyms_to_the_live_indexes_and_to_rebuilds_in_flight(): void
    {
        // A rebuild in flight applied the synonyms when it started, and its swap would revert the update
        $index = new Index('products', ProductDocument::class, [Product::class], new Container());
        $indexRegistry = new IndexRegistry();
        $indexRegistry->add($index);

        $indexScope = new IndexScope($index, 'FASHION_WEB', 'en_US', 'USD');

        $indexScopeProvider = $this->prophesize(IndexScopeProviderInterface::class);
        $indexScopeProvider->getAll($index)->willReturn([$indexScope]);

        $uidResolver = $this->prophesize(IndexUidResolverInterface::class);
        $uidResolver->resolveFromIndexScope($indexScope)->willReturn('products__a');

        $synonymResolver = $this->prophesize(SynonymResolverInterface::class);
        $synonymResolver->resolve($indexScope)->willReturn(['jeans' => ['denim']]);

        $rebuildUid = RebuildUid::from('products__a', RebuildUid::generateId('ab12cd'));

        $listedIndex = $this->prophesize(Indexes::class);
        $listedIndex->getUid()->willReturn($rebuildUid);

        $client = $this->prophesize(Client::class);
        $client->getIndexes(Argument::type(IndexesQuery::class))->willReturn(new IndexesResults([
            'results' => [$listedIndex->reveal()],
            'offset' => 0,
            'limit' => 200,
            'total' => 1,
        ]));
        foreach (['products__a', $rebuildUid] as $uid) {
            $indexes = $this->prophesize(Indexes::class);
            $indexes->updateSynonyms(['jeans' => ['denim']])->shouldBeCalledOnce()->willReturn([]);
            $client->index($uid)->willReturn($indexes->reveal());
        }

        $handler = new UpdateSynonymsHandler(
            $client->reveal(),
            $indexRegistry,
            $indexScopeProvider->reveal(),
            $uidResolver->reveal(),
            $synonymResolver->reveal(),
        );

        $handler(new UpdateSynonyms());
    }
}
