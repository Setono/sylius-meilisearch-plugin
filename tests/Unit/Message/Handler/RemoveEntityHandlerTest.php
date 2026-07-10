<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Unit\Message\Handler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Document\Product as ProductDocument;
use Setono\SyliusMeilisearchPlugin\Indexer\IndexerInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\RemoveEntity;
use Setono\SyliusMeilisearchPlugin\Message\Handler\RemoveEntityHandler;
use Setono\SyliusMeilisearchPlugin\Tests\Application\Entity\Product;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Message\Handler\RemoveEntityHandler
 */
final class RemoveEntityHandlerTest extends TestCase
{
    use ProphecyTrait;

    private function createIndex(IndexerInterface $indexer): Index
    {
        $locator = new Container();
        $locator->set(IndexerInterface::class, $indexer);

        return new Index('products', ProductDocument::class, [Product::class], $locator);
    }

    /**
     * @test
     */
    public function it_removes_the_document_by_id_without_reloading_the_entity(): void
    {
        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->removeDocuments(['42'])->shouldBeCalled();

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getByEntity(Product::class)->willReturn(['products' => $this->createIndex($indexer->reveal())]);

        $handler = new RemoveEntityHandler($indexRegistry->reveal());
        // The entity no longer exists in the DB (this is a remove), yet the handler must not throw:
        // it removes the document using the identifier the message carries, not by reloading the entity.
        $handler(new RemoveEntity(Product::class, 42, '42'));
    }

    /**
     * @test
     */
    public function it_does_nothing_when_the_message_has_no_document_identifier(): void
    {
        $indexer = $this->prophesize(IndexerInterface::class);
        $indexer->removeDocuments(Argument::cetera())->shouldNotBeCalled();

        $indexRegistry = $this->prophesize(IndexRegistryInterface::class);
        $indexRegistry->getByEntity(Argument::cetera())->shouldNotBeCalled();

        $handler = new RemoveEntityHandler($indexRegistry->reveal());
        $handler(new RemoveEntity(Product::class, 42, null));
    }
}
