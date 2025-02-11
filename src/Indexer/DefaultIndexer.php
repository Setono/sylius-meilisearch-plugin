<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EntityFilterInterface as ObjectFilterInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

/**
 * NOT final as this makes it easier to override and extend this indexer
 */
class DefaultIndexer extends AbstractIndexer
{
    use ORMTrait;

    public function __construct(
        protected readonly Index $index,
        ManagerRegistry $managerRegistry,
        protected readonly IndexScopeProviderInterface $indexScopeProvider,
        protected readonly IndexUidResolverInterface $indexNameResolver,
        protected readonly DataMapperInterface $dataMapper,
        protected readonly NormalizerInterface $normalizer,
        protected readonly Client $client,
        protected readonly ObjectFilterInterface $objectFilter,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly MessageBusInterface $commandBus,
        protected readonly ValidatorInterface $validator,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function index(): void
    {
        foreach ($this->index->entities as $entity) {
            /** @var IndexBuffer<string|int> $buffer */
            $buffer = new IndexBuffer(100, fn (array $ids) => $this->commandBus->dispatch(IndexEntities::fromIds($entity, $ids)));

            foreach ($this->index->dataProvider()->getIds($entity, $this->index) as $id) {
                $buffer->push($id);
            }

            $buffer->flush();
        }
    }

    public function indexEntities(array $entities): void
    {
        if ([] === $entities) {
            return;
        }

        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            $documents = [];

            foreach ($entities as $entity) {
                $document = new $this->index->document();
                $this->dataMapper->map($entity, $document, $indexScope);

                if (!$this->objectFilter->filter($entity, $document, $indexScope)) {
                    continue;
                }

                if ($this->validator->validate($document)->count() > 0) {
                    continue;
                }

                $data = $this->normalizer->normalize($document);
                Assert::isArray($data);

                $documents[] = $data;
            }

            $this->client->index($this->indexNameResolver->resolveFromIndexScope($indexScope))->addDocuments($documents, 'id');
        }
    }

    public function removeEntities(array $entities): void
    {
        if ([] === $entities) {
            return;
        }

        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            foreach ($entities as $entity) {
                $this->client->index($this->indexNameResolver->resolveFromIndexScope($indexScope))->deleteDocument($entity->getDocumentIdentifier());
            }
        }
    }
}
