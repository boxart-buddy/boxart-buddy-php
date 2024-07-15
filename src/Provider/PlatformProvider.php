<?php

namespace App\Provider;

use App\Config\Reader\ConfigReader;

readonly class PlatformProvider
{
    public function __construct(
        private PathProvider $pathProvider,
        private ConfigReader $configReader
    ) {
    }

    public function getPlatform(string $path): string
    {
        $platform = $this->getPlatformOrNull($path);
        if (!$platform) {
            throw new \InvalidArgumentException(sprintf('Cannot find platform for folder: %s', $path));
        }

        return $platform;
    }

    public function getPlatformOrNull(string $path): ?string
    {
        if (!is_dir($path)) {
            $path = dirname($path);
        }

        $path = $this->pathProvider->removeRomFolderBase($path);
        $path = $this->pathProvider->removeOutputPathBase($path);

        if ('folder' === strtolower($path)) {
            return 'folder';
        }

        return $this->configReader->getConfig()->getPlatformForFolder($path);
    }
}
