<?php

namespace App\Builder;

use App\Command\CommandNamespace;
use App\Config\Reader\ConfigReader;
use App\Provider\PathProvider;
use App\Provider\PlatformProvider;

readonly class SkyscraperCommandDirector
{
    public function __construct(
        private ConfigReader $configReader,
        private PathProvider $pathProvider,
        private PlatformProvider $platformProvider
    ) {
    }

    public function getScrapeCommand(string $folderAbsolutePath, bool $onlyMissing = false): array
    {
        $commandBuilder = new SkyscraperCommandBuilder();

        $config = $this->configReader->getConfig();

        $platform = $this->platformProvider->getPlatform($folderAbsolutePath);

        $commandBuilder
            ->setCredentials($config->getScreenScraperCredentials())
            ->addFlag('unattend')
            ->addFlag('unpack')
            ->addFlag('nohints')
            ->setPlatform($platform)
            ->setScraper('screenscraper')
            ->setInputPath($folderAbsolutePath)
            ->setThreads($config->scrapeThreads);

        if ($onlyMissing) {
            $commandBuilder->addFlag('onlymissing');
        }

        $commandBuilder->addExt('*.sh');

        return $commandBuilder->build();
    }

    public function getScrapeCommandForSingleRom(
        string $romAbsolutePath,
        bool $onlyMissing,
        ?string $query = null,
        ?string $platform = null
    ): array {
        $commandBuilder = new SkyscraperCommandBuilder();

        $config = $this->configReader->getConfig();

        if (!$platform) {
            $platform = $this->platformProvider->getPlatform($romAbsolutePath);
        }

        $commandBuilder->setCredentials($config->getScreenScraperCredentials())
            ->addFlag('unattend')
            ->addFlag('unpack')
            ->addFlag('nohints')
            ->setPlatform($platform)
            ->setScraper('screenscraper')
            ->setInputPath(dirname($romAbsolutePath))
            ->setRomName(basename($romAbsolutePath));

        if ($onlyMissing) {
            $commandBuilder->addFlag('onlymissing');
        }

        $commandBuilder->addExt('*.sh');

        if (null !== $query) {
            $commandBuilder->setQuery($query);
        }

        return $commandBuilder->build();
    }

    public function getImportLocalDataCommand(
        string $platform,
        string $inputPath
    ): array {
        $commandBuilder = new SkyscraperCommandBuilder();
        $commandBuilder
            ->setScraper('import')
            ->setPlatform($platform)
            ->setInputPath($inputPath);

        $commandBuilder->addExt('*.sh');

        return $commandBuilder->build();
    }

    public function getRomBoxartGenerateCommand(
        string $artworkPath,
        string $platform,
        string $romAbsolutePath,
        CommandNamespace $namespace
    ): array {
        $inFolder = dirname($romAbsolutePath);
        $outFolder = $this->pathProvider->getOutputPathForGeneratedArtworkForNamespace($romAbsolutePath, $namespace);

        $commandBuilder = new SkyscraperCommandBuilder();
        $commandBuilder
            ->setArtworkPath($artworkPath)
            ->setInputPath($inFolder)
            ->setOutputPath($outFolder)
            ->setGamelistPath($this->pathProvider->getGamelistPath($this->pathProvider->removeRomFolderBase(dirname($romAbsolutePath))))
            ->setPlatform($platform)
            ->addFlag('unattend')
            ->addFlag('unpack')
            ->addFlag('nohints')
            ->setVerbosity(3)
            ->setRomName(basename($romAbsolutePath));

        $commandBuilder->addExt('*.sh');

        return $commandBuilder->build();
    }
}
