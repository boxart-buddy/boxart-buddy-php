<?php

namespace App\ConsoleCommand;

use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Importer\SkippedRomImporter;
use App\Importer\SkyscraperManualDataImporter;
use App\Provider\PlatformProvider;
use App\Util\Console\BlockSectionHelper;
use App\Util\Path;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'import-skipped',
    description: 'Imports `skipped` roms from the skipped folder into the cache',
)]
class ImportSkippedCommand extends Command
{
    public function __construct(
        readonly private LoggerInterface $logger,
        readonly private SkippedRomImporter $skippedRomImporter,
        readonly private ConfigReader $configReader,
        readonly private Path $path,
        readonly private SkyscraperManualDataImporter $skyscraperManualDataImporter,
        readonly private PlatformProvider $platformProvider
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('mode', null, 'use `json` to rescrape using missing.json or `files` to use the generated files', 'json', ['json', 'files']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->getArgument('mode');

        $io = new BlockSectionHelper($input, $output, $this->logger);
        $io->heading();

        $io->section('import');

        if ('json' === $mode) {
            $io->wait('Importing skipped data to skyscraper using `missing.json`');

            try {
                $this->skippedRomImporter->import();
            } catch (\Throwable $e) {
                $io->failure($e->getMessage(), true);

                return Command::FAILURE;
            }

            $io->complete('Import Complete', true);
        }

        if ('files' === $mode) {
            $io->wait('Importing skipped data using files in `skipped/*` folders');

            try {
                $this->importFromFiles($input, $output);
            } catch (\Throwable $e) {
                $io->failure($e->getMessage(), true);

                return Command::FAILURE;
            }

            $io->complete('Import Complete', true);
        }

        return Command::SUCCESS;
    }

    private function importFromFiles(InputInterface $input, OutputInterface $output): void
    {
        $io = new BlockSectionHelper($input, $output);

        $config = $this->configReader->getConfig();
        $romsetName = $config->romsetName;

        $finder = new Finder();
        $finder->in($this->path->joinWithBase(FolderNames::SKIPPED->value, $romsetName))->directories();

        $importPaths = [];
        foreach ($finder as $directory) {
            $importPaths[] = $directory->getRealPath();
        }

        if (empty($importPaths)) {
            $io->help('Nothing to import', true);

            return;
        }

        $progressBar = $io->getProgressBar();

        foreach ($progressBar->iterate($importPaths) as $sourceDirectory) {
            $progressBar->setMessage($sourceDirectory);
            $romSourceFolder = Path::remove($this->path->removeBase($sourceDirectory), FolderNames::SKIPPED->value);
            $platform = $this->platformProvider->getPlatform($romSourceFolder);
            $romAbsolutePath = Path::join($this->configReader->getConfig()->romFolder, $romSourceFolder);
            $this->skyscraperManualDataImporter->importResources($sourceDirectory, $platform, $romAbsolutePath);
        }
    }
}
