<?php

namespace App\Config\Validator;

use App\Config\InvalidConfigException;
use App\Config\Reader\ConfigReader;
use App\Skyscraper\RomExtensionProvider;
use App\Util\Path;
use Symfony\Component\Finder\Finder;

readonly class ConfigValidator
{
    public function __construct(
        private ConfigReader $configReader,
        private RomExtensionProvider $romExtensionProvider
    ) {
    }

    public function getPlatformReport(): array
    {
        $config = $this->configReader->getConfig();
        $folders = $config->folders;
        $romFolder = $config->romFolder;
        if (!file_exists(Path::join($romFolder))) {
            throw new InvalidConfigException(sprintf('Configured `rom_folder`: `%s` does not exist', $romFolder));
        }

        $report = [];
        foreach ($folders as $folder => $platform) {
            $folderPath = Path::join($romFolder, $folder);
            if (!file_exists($folderPath)) {
                throw new InvalidConfigException(sprintf('Configured `%s` folder does not exist', $folder));
            }
            $finder = new Finder();
            $finder->in($folderPath)->files()->depth(0);
            $this->romExtensionProvider->addRomExtensionsToFinder($finder, $platform);
            $count = count($finder);
            $report[$folder] = [
                'platform' => $platform,
                'count' => $count,
            ];
        }

        ksort($report);

        return $report;
    }
}
