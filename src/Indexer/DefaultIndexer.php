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
use Setono\SyliusMeilisearchPlugin\Meilisearch\RebuildIndexes;
use Setono\SyliusMeilisearchPlugin\Message\Command\IndexEntities;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScope;
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

    public function index(Rebuild $rebuild): int
    {
        $batches = 0;

        foreach ($this->index->entities as $entity) {
            /** @var IndexBuffer<string|int> $buffer */
            $buffer = new IndexBuffer(
                100,
                /** @param list<string|int> $ids */
                function (array $ids) use ($entity, $rebuild, &$batches): void {
                    ++$batches;

                    // The batch is constrained to this index: without that, the handler would fan it
                    // out to every index configured for the entity class, and a rebuild batch would
                    // write into rebuild indexes that are never swapped
                    $this->commandBus->dispatch(IndexEntities::fromIds($entity, $ids, $this->index->name, $rebuild, $batches));
                },
            );

            foreach ($this->index->dataProvider()->getIds($entity, $this->index) as $id) {
                $buffer->push($id);
            }

            $buffer->flush();
        }

        return $batches;
    }

    public function indexEntities(array $entities, ?Rebuild $rebuild = null): void
    {
        if ([] === $entities) {
            return;
        }

        /** @var list<array{IndexScope, string}> $scopes */
        $scopes = [];
        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            $liveUid = $this->indexNameResolver->resolveFromIndexScope($indexScope);

            // A scope that appeared after the rebuild started has no rebuild index (and is not part of
            // the swap) — its live index is created by the next rebuild
            if (null !== $rebuild && !$rebuild->covers($liveUid)) {
                continue;
            }

            $scopes[] = [$indexScope, $liveUid];
        }

        $inFlight = null === $rebuild ? RebuildIndexes::inFlight($this->client, array_column($scopes, 1)) : [];

        foreach ($scopes as [$indexScope, $liveUid]) {
            $uids = null === $rebuild ? [$liveUid, ...$inFlight[$liveUid] ?? []] : [$rebuild->uidOf($liveUid)];

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
                    // This also applies when rebuilding: a retried batch may already have written the
                    // entity's document before it was filtered out.
                    $documentIdentifier = $entity->getDocumentIdentifier();
                    if (null !== $documentIdentifier) {
                        $documentsToRemove[] = $documentIdentifier;
                    }

                    $this->logger->debug('Entity was filtered out during indexing and removed from the index if present', [
                        'index' => $liveUid,
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
                        'index' => $liveUid,
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

            foreach ($uids as $uid) {
                $meilisearchIndex = $this->client->index($uid);

                // Skip the call for an empty batch so we don't create pointless empty Meilisearch tasks
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

        $liveUids = [];
        foreach ($this->indexScopeProvider->getAll($this->index) as $indexScope) {
            $liveUids[] = $this->indexNameResolver->resolveFromIndexScope($indexScope);
        }

        // A rebuild in flight may have indexed the documents before their removal, and its swap would
        // bring them back — so they are removed from its rebuild indexes too
        $inFlight = RebuildIndexes::inFlight($this->client, $liveUids);

        foreach ($liveUids as $liveUid) {
            foreach ([$liveUid, ...$inFlight[$liveUid] ?? []] as $uid) {
                // One batch deleteDocuments task per scope instead of one deleteDocument task per id
                $this->client->index($uid)->deleteDocuments($documentIds);

                $this->logger->info('Removed documents from index', [
                    'index' => $uid,
                    'ids' => $documentIds,
                ]);
            }
        }
    }
}
