<?php

namespace App\Importer;

use App\Builder\SkyscraperCommandDirector;
use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Provider\PathProvider;
use App\Util\Path;
use App\Util\Time;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[WithMonologChannel('skyscraper')]
readonly class SkyscraperManualDataImporter
{
    public function __construct(
        private SkyscraperCommandDirector $skyscraperCommandDirector,
        private LoggerInterface $logger,
        private ConfigReader $configReader,
        private Path $path,
        private PathProvider $pathProvider,
        private Time $time
    ) {
    }

    public function importResources(string $sourceDirectory, string $platform, string $romAbsolutePath): void
    {
        $config = $this->configReader->getConfig();

        $skyscraperConfigFolderPath = $config->skyscraperConfigFolderPath;

        $filesystem = new Filesystem();

        // definitions copy
        $definitionsIn = $this->path->joinWithBase('resources', 'definitions.dat');
        $definitionsOut = Path::join($skyscraperConfigFolderPath, 'import', 'definitions.dat');
        if ($filesystem->exists($definitionsOut)) {
            $filesystem->remove($definitionsOut);
        }
        $filesystem->copy($definitionsIn, $definitionsOut);

        // copy all files from temp to skyscraper first
        $importOut = Path::join($skyscraperConfigFolderPath, 'import', $platform);

        $filesystem->mirror(
            $sourceDirectory,
            $importOut
        );

        // run import command to import into cache
        $command = $this->skyscraperCommandDirector->getImportLocalDataCommand(
            $platform,
            $romAbsolutePath,
        );

        $this->logger->debug('Importing into portmaster with following command');
        $this->logger->debug(implode(', ', $command));

        $process = new Process($command);
        $process->setTimeout(60);

        try {
            $process->run();

            $output = $process->getOutput();
            $this->logger->debug($output);
            if (!$process->isSuccessful()) {
                $this->logger->error($process->getErrorOutput());
                throw new \RuntimeException(sprintf('The skyscraper import failed. Check `%s`', $this->pathProvider->getLogPath('skyscraper')));
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \RuntimeException(sprintf('The skyscraper import failed. Check `%s`', $this->pathProvider->getLogPath('skyscraper')));
        }

        // remove from ss import
        // $filesystem->remove($importOut);

        // move import in to temp location
        $tempPath = $this->path->joinWithBase(FolderNames::TEMP->value, 'import-files-bak', (string) $this->time->getInitTime());
        $filesystem->mirror($sourceDirectory, $tempPath);
        $filesystem->remove($sourceDirectory);
    }
}
