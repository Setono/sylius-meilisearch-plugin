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

    public function index(): void
    {
        foreach ($this->index->entities as $entity) {
            /** @var IndexBuffer<string|int> $buffer */
            $buffer = new IndexBuffer(
                100,
                /** @param list<string|int> $ids */
                function (array $ids) use ($entity): void {
                    $this->commandBus->dispatch(IndexEntities::fromIds($entity, $ids));
                },
            );

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
            $uid = $this->indexNameResolver->resolveFromIndexScope($indexScope);

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

            // Skip the addDocuments call for an empty batch (e.g. everything was filtered out)
            // so we don't create pointless empty Meilisearch tasks.
            if ([] !== $documents) {
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
