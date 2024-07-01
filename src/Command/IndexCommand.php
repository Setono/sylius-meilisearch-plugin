<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Command;

use Setono\SyliusMeilisearchPlugin\Config\IndexRegistry;
use Setono\SyliusMeilisearchPlugin\Message\Command\Index;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'setono:sylius-meilisearch:index',
    description: 'Will index all configured indexes'
)]
final class IndexCommand extends Command
{

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly IndexRegistry $indexRegistry
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->indexRegistry as $index) {
            $this->commandBus->dispatch(new Index($index));
        }

        return 0;
    }
}
