<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Indexer;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusMeilisearchPlugin\Config\Index;
use Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface;
use Setono\SyliusMeilisearchPlugin\Filter\Entity\EntityFilterInterface as ObjectFilterInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
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
        protected readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function index(string $rebuildId): int
    {
        $batches = 0;

        foreach ($this->index->entities as $entity) {
            /** @var IndexBuffer<string|int> $buffer */
            $buffer = new IndexBuffer(
                100,
                /** @param list<string|int> $ids */
                function (array $ids) use ($entity, $rebuildId, &$batches): void {
                    ++$batches;

                    // The batch is constrained to this index: without that, the handler would fan it
                    // out to every index configured for the entity class, and a rebuild batch would
                    // write into rebuild indexes that are never swapped
                    $this->commandBus->dispatch(IndexEntities::fromIds($entity, $ids, $this->index->name, $rebuildId));
                },
            );

            foreach ($this->index->dataProvider()->getIds($entity, $this->index) as $id) {
                $buffer->push($id);
            }

            $buffer->flush();
        }

        return $batches;
    }

    public function indexEntities(array $entities, ?string $rebuildId = null): void
    {
        if ([] === $entities) {
            return;
        }

        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            $uid = $this->indexNameResolver->resolveFromIndexScope($indexScope);
            if (null !== $rebuildId) {
                $uid = RebuildUid::from($uid, $rebuildId);
            }

            $documents = [];
            $documentsToRemove = [];
            $filtered = 0;
            $invalid = 0;

            foreach ($entities as $entity) {
                $document = new $this->index->document();
                $this->dataMapper->map($entity, $document, $indexScope);

                if (!$this->objectFilter->filter($entity, $document, $indexScope)) {
                    ++$filtered;

                    // Actively remove a filtered-out entity from the index (e.g. a product that was
                    // just disabled or went out of stock) instead of silently leaving a stale document.
                    $documentIdentifier = $entity->getDocumentIdentifier();
                    if (null !== $documentIdentifier) {
                        $documentsToRemove[] = $documentIdentifier;
                    }

                    $this->logger->debug('Entity was filtered out during indexing and removed from the index if present', [
                        'index' => $uid,
                        'entity' => $entity::class,
                        'id' => $entity->getDocumentIdentifier(),
                    ]);

                    continue;
                }

                $violations = $this->validator->validate($document);
                if ($violations->count() > 0) {
                    ++$invalid;

                    $messages = [];
                    foreach ($violations as $violation) {
                        $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), (string) $violation->getMessage());
                    }

                    $this->logger->warning('Document failed validation during indexing and was skipped', [
                        'index' => $uid,
                        'entity' => $entity::class,
                        'id' => $entity->getDocumentIdentifier(),
                        'violations' => $messages,
                    ]);

                    continue;
                }

                $data = $this->normalizer->normalize($document);
                Assert::isArray($data);

                $documents[] = $data;
            }

            $meilisearchIndex = $this->client->index($uid);

            // When rebuilding, every batch must create exactly one document-addition task per scope
            // — even an empty one — because the finalization counts these tasks to decide when the
            // rebuild is complete (see FinalizeIndexRebuild). Outside a rebuild we skip the call
            // for an empty batch so we don't create pointless empty Meilisearch tasks.
            if (null !== $rebuildId || [] !== $documents) {
                $meilisearchIndex->addDocuments($documents, 'id');
            }

            if ([] !== $documentsToRemove) {
                $meilisearchIndex->deleteDocuments($documentsToRemove);
            }

            $this->logger->info('Indexed a batch of entities', [
                'index' => $uid,
                'mapped' => count($entities),
                'filtered' => $filtered,
                'invalid' => $invalid,
                'indexed' => count($documents),
                'removed' => count($documentsToRemove),
            ]);
        }
    }

    public function removeEntities(array $entities): void
    {
        if ([] === $entities) {
            return;
        }

        $ids = [];
        foreach ($entities as $entity) {
            $documentIdentifier = $entity->getDocumentIdentifier();
            if (null !== $documentIdentifier) {
                $ids[] = $documentIdentifier;
            }
        }

        $this->removeDocuments($ids);
    }

    public function removeDocuments(array $documentIds): void
    {
        if ([] === $documentIds) {
            return;
        }

        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            $uid = $this->indexNameResolver->resolveFromIndexScope($indexScope);

            // One batch deleteDocuments task per scope instead of one deleteDocument task per id
            $this->client->index($uid)->deleteDocuments($documentIds);

            $this->logger->info('Removed documents from index', [
                'index' => $uid,
                'ids' => $documentIds,
            ]);
        }
    }
}
