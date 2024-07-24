<?php

namespace App\Provider;

use App\Config\Reader\ConfigReader;
use App\Importer\FakeRomImporter;
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
        private PathProvider $pathProvider,
        private LoggerInterface $logger
    ) {
    }

    public function getSingleRomByFolder(string $folderAbsolutePath): ?string
    {
        // try to match with the configured rom name if provided
        $singleRom = $this->configReader->getConfig()->getSingleRomForPlatform(
            $this->platformProvider->getPlatform($folderAbsolutePath)
        );

        if ($singleRom) {
            $finder = new Finder();
            $finder->in($folderAbsolutePath);
            $finder->files()->exactName($singleRom, true);

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
            $this->logger->debug(sprintf('Folder is empty, providing fake rom for folder `%s`', $folderAbsolutePath));

            return Path::join($this->pathProvider->getFakeRomPath(), $platform, FakeRomImporter::FAKE_ROM_NAME.'.zip');
        }

        return $finder->first()->getRealPath();
    }
}
