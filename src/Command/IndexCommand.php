<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Command;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexScope\IndexScopeProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\IndexUidResolverInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'setono:sylius-meilisearch:index',
    description: 'Will index all configured indexes',
)]
final class IndexCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly IndexRegistryInterface $indexRegistry,
        private readonly Client $client,
        private readonly IndexScopeProviderInterface $indexScopeProvider,
        private readonly IndexUidResolverInterface $indexUidResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'indexes',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Names of the index(es) to index',
                $this->indexRegistry->getNames(),
            )
            ->addOption('wait', 'w', InputOption::VALUE_NONE, 'Wait for the indexing to finish')
            ->addOption('wait-timeout', 't', InputOption::VALUE_REQUIRED, 'The maximum time to wait for the indexing to finish in seconds. This is only relevant if you have enabled the "wait" option', 300)
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete index before creating')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        /** @var list<string> $indexes */
        $indexes = $input->getArgument('indexes');

        foreach ($indexes as $index) {
            if (!$this->indexRegistry->has($index)) {
                throw new RuntimeException(sprintf(
                    'No index exists with the name "%s". Available indexes are: [%s]',
                    $index,
                    implode(', ', $this->indexRegistry->getNames()),
                ));
            }
        }

        $waitTimeout = $input->getOption('wait-timeout');

        if (!is_numeric($waitTimeout)) {
            throw new RuntimeException('The wait-timeout option must be a number');
        }

        $waitTimeout = (int) $waitTimeout;

        if ($waitTimeout <= 0) {
            throw new RuntimeException('The wait-timeout option must be greater than 0');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $indexes */
        $indexes = $input->getArgument('indexes');
        $delete = (bool) $input->getOption('delete');

        if ($delete) {
            $output->writeln('<comment>WARNING: --delete is enabled — each index is deleted before it is rebuilt, so search returns no results for that index until reindexing completes. A plain reindex (without --delete) upserts in place and avoids this downtime.</comment>');
        }

        $uids = [];

        foreach ($indexes as $index) {
            $indexUids = $this->resolveIndexUidsForIndex($index);
            foreach ($indexUids as $uid) {
                $uids[$uid] = $uid;
            }

            $output->writeln(sprintf('Indexing <info>%s</info> → %s', $index, implode(', ', $indexUids)));

            $this->commandBus->dispatch(new Index($index, $delete));
        }

        $uids = array_values($uids);

        /** @var bool $wait */
        $wait = $input->getOption('wait');

        if ($wait) {
            $this->wait($uids, (int) $input->getOption('wait-timeout'), $output);
            $this->printSummary($uids, $output);
        }

        return 0;
    }

    /**
     * Resolves the concrete Meilisearch index uids (across all scopes) for a single index name.
     *
     * @return list<string>
     */
    private function resolveIndexUidsForIndex(string $index): array
    {
        $uids = [];

        foreach ($this->indexScopeProvider->getAll($this->indexRegistry->get($index)) as $indexScope) {
            $uid = $this->indexUidResolver->resolveFromIndexScope($indexScope);
            $uids[$uid] = $uid;
        }

        return array_values($uids);
    }

    /**
     * Prints the resulting document count per resolved uid. Only meaningful once the Meilisearch
     * tasks have finished, which is why it is called after --wait.
     *
     * @param list<string> $uids
     */
    private function printSummary(array $uids, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['Index uid', 'Documents']);

        foreach ($uids as $uid) {
            try {
                $stats = $this->client->index($uid)->stats();
                $documents = $stats['numberOfDocuments'] ?? null;
                $count = is_scalar($documents) ? (string) $documents : '?';
            } catch (\Throwable) {
                $count = 'n/a';
            }

            $table->addRow([$uid, $count]);
        }

        $output->writeln('');
        $output->writeln('<info>Index summary</info>');
        $table->render();
    }

    /**
     * @param list<string> $indexUids
     */
    private function wait(array $indexUids, int $waitTimeout, OutputInterface $output): void
    {
        $start = time();

        $query = self::createTasksQuery($indexUids);

        do {
            $results = $this->client->getTasks($query);

            if ($results->getTotal() === 0) {
                return;
            }

            $output->writeln(sprintf('Waiting for %d tasks to finish...', $results->getTotal()));

            sleep(10);
        } while ((time() - $start) < $waitTimeout);

        throw new RuntimeException(sprintf('The indexing did not finish within the specified time (%d seconds)', $waitTimeout));
    }

    /**
     * @param list<string> $indexUids
     */
    public static function createTasksQuery(array $indexUids): TasksQuery
    {
        $query = new TasksQuery();
        $query->setStatuses(['enqueued', 'processing']);

        // Scope the query to this run's index uids so we don't wait on unrelated tasks from
        // other developers/environments sharing the same Meilisearch instance (the exact
        // scenario MEILISEARCH_PREFIX exists for).
        if ([] !== $indexUids) {
            $query->setIndexUids($indexUids);
        }

        return $query;
    }
}
