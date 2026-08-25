<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Command;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Setono\SyliusMeilisearchPlugin\Config\IndexRegistryInterface;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Setono\SyliusMeilisearchPlugin\Provider\IndexUids\IndexUidsProviderInterface;
use Setono\SyliusMeilisearchPlugin\Resolver\IndexUid\RebuildUid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal wired through the container; not part of the plugin's public API
 */
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
        private readonly IndexUidsProviderInterface $indexUidsProvider,
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
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'DEPRECATED: has no effect. A plain run rebuilds each index atomically and purges stale documents')
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

        if ((bool) $input->getOption('delete')) {
            trigger_deprecation('setono/sylius-meilisearch-plugin', '0.3', 'The --delete option of the setono:sylius-meilisearch:index command is deprecated and has no effect.');

            $output->writeln('<comment>The --delete option is deprecated and has no effect: a plain run now rebuilds each index into a temporary index and atomically swaps it with the live index, which purges stale documents without any search downtime.</comment>');
        }

        $liveUids = [];

        foreach ($indexes as $index) {
            $indexUids = $this->indexUidsProvider->get($index);
            foreach ($indexUids as $uid) {
                $liveUids[$uid] = $uid;
            }

            $output->writeln(sprintf('Indexing <info>%s</info> → %s', $index, implode(', ', $indexUids)));

            $this->commandBus->dispatch(new Index($index));
        }

        $liveUids = array_values($liveUids);

        /** @var bool $wait */
        $wait = $input->getOption('wait');

        if ($wait) {
            // The rebuild happens in the rebuild indexes until the atomic swap at the end, so the
            // tasks worth waiting for live on both the live uid (the swap and its cleanup) and the
            // rebuild uid (the document additions).
            $waitUids = $liveUids;
            foreach ($liveUids as $uid) {
                $waitUids[] = RebuildUid::from($uid);
            }

            $this->wait($waitUids, (int) $input->getOption('wait-timeout'), $output);
            $this->printSummary($liveUids, $output);
        }

        return 0;
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
