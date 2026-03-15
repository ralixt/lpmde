<?php

namespace App\Command;

use App\Message\GhostAlert;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:dispatch:ghost-alert',
    description: 'Publie un message GhostAlert sur le transport async.',
)]
class DispatchGhostAlertCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('location', null, InputOption::VALUE_REQUIRED, 'Lieu de l alerte.', 'Cave a vin')
            ->addOption('monster', null, InputOption::VALUE_REQUIRED, 'Type de monstre.', 'Poltergeist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $location = (string) $input->getOption('location');
        $monster = (string) $input->getOption('monster');

        $this->bus->dispatch(new GhostAlert($location, $monster));

        $io->success(sprintf('Message GhostAlert publie: %s dans %s.', $monster, $location));

        return Command::SUCCESS;
    }
}