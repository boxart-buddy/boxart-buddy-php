<?php

namespace App\ConsoleCommand;

use App\Command\Factory\CommandFactory;
use App\Command\Handler\CentralHandler;
use App\Config\Reader\ConfigReader;
use App\Config\Validator\ConfigValidator;
use App\Lock\LockIO;
use App\Portmaster\PortmasterDataImporter;
use App\Util\Console\BlockSectionHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'scrape',
    description: 'Scrapes artwork using Skyscraper (from Screenscraper.fr)',
    aliases: ['prime-cache']
)]
class ScrapeCommand extends Command
{
    use PlatformOverviewTrait;
    use PreflightCheckTrait;

    public function __construct(
        readonly private CommandFactory $commandFactory,
        readonly private CentralHandler $centralHandler,
        readonly private ConfigValidator $configValidator,
        readonly private LoggerInterface $logger,
        readonly private PortmasterDataImporter $portmasterDataImporter,
        readonly private ConfigReader $configReader,
        readonly private LockIO $lockIO
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('onlymissing', null, InputOption::VALUE_NONE, 'if set then will only attempt to scrape for missing roms');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutput) {
            throw new \RuntimeException();
        }

        $io = new BlockSectionHelper($input, $output, $this->logger);
        $io->heading();

        $this->runPreflightChecks($io, $this->configReader, $this->lockIO);
        $this->printPlatformOverview($io, $this->configValidator);

        $onlyMissing = $input->getOption('onlymissing');
        $commands = $this->commandFactory->createPrimeCacheCommands($onlyMissing);

        if ($commands) {
            $io->section('prime-cache');

            $io->wait('Priming Cache (using screenscraper) (SLOW ON FIRST RUN)');

            $progressBar = $io->getProgressBar();

            foreach ($progressBar->iterate($commands) as $command) {
                $progressBar->setMessage($command->folderAbsolutePath);

                $this->centralHandler->handle($command);
            }

            $io->done('Scraping for platforms complete', true);
        }

        $io->section('prime-cache-screenscraper-alternates');
        $io->wait('Scraping for portmaster alternates');
        try {
            $this->portmasterDataImporter->scrapeUsingAlternatesList($onlyMissing);
            $io->done('Scraping for portmaster alternates complete', true);
        } catch (\Throwable $exception) {
            $io->failure('Scraping for portmaster alternates failed', true);
        }

        return Command::SUCCESS;
    }
}
