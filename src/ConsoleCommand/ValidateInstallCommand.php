<?php

namespace App\ConsoleCommand;

use App\Config\Reader\ConfigReader;
use App\Util\Console\BlockSectionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'validate-install',
)]
class ValidateInstallCommand extends Command
{
    use PreflightCheckTrait;

    public function __construct(readonly private ConfigReader $configReader)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output);

        $io->section('validate-install');

        $this->checkSkyscraperInstalled($io);
        $io->done('Skyscraper Installed');

        $this->checkPeasPresent($io, $this->configReader);
        $io->done('Skyscraper Config Files Present');

        $io->complete('Installation is valid! `cd boxart-buddy` then run `make bootstrap`, `make scrape` and `make build`');

        return Command::SUCCESS;
    }
}
