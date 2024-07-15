<?php

namespace App\Provider;

use App\Config\Reader\ConfigReader;
use App\Skyscraper\RomExtensionProvider;
use App\Util\Finder;
use App\Util\Path;
use Psr\Log\LoggerInterface;

readonly class FolderRomProvider
{
    public function __construct(
        private ConfigReader $configReader,
        private RomExtensionProvider $romExtensionProvider,
        private PlatformProvider $platformProvider,
        private LoggerInterface $logger
    ) {
    }

    public function getSingleRomByFolder(string $folderAbsolutePath): ?string
    {
        // try to match with the configured rom name if provided
        $singleRom = $this->configReader->getConfig()->getSingleRomForFolder(
            Path::remove($folderAbsolutePath, $this->configReader->getConfig()->romFolder)
        );

        if ($singleRom) {
            $finder = new Finder();
            $finder->in($folderAbsolutePath);
            $finder->files()->name($singleRom.'.*');

            if ($finder->hasResults()) {
                return $finder->first()->getRealPath();
            }
        }

        // if nothing found or configured then just return the first found
        $finder = new Finder();
        $finder->in($folderAbsolutePath);

        // some allowances for dirty rom folders

        $platform = $this->platformProvider->getPlatform($folderAbsolutePath);
        $finder->files();
        $this->romExtensionProvider->addRomExtensionsToFinder($finder, $platform);

        if (!$finder->hasResults()) {
            $this->logger->warning(sprintf('Cannot get single ROM from `%s`, no files in this location', $folderAbsolutePath));

            return null;
        }

        return $finder->first()->getRealPath();
    }
}
