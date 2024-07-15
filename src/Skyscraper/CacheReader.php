<?php

namespace App\Skyscraper;

use App\Config\Reader\ConfigReader;
use App\Util\ImageSizing;
use App\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

readonly class CacheReader
{
    public function __construct(
        private ConfigReader $configReader
    ) {
    }

    private function getImageSizeForRom(string $absoluteRomPath, string $platform, string $resource): array
    {
        $filesystem = new Filesystem();

        $cachePath = $this->configReader->getConfig()->skyscraperCacheFolderPath;
        $quickIdPath = Path::join($cachePath, $platform, 'quickid.xml');
        $dbPath = Path::join($cachePath, $platform, 'db.xml');

        if (!$filesystem->exists($dbPath) || !$filesystem->exists($quickIdPath)) {
            return [];
        }

        $xml = simplexml_load_file($quickIdPath);
        if (!$xml) {
            return [];
        }

        $results = $xml->xpath(
            sprintf('//quickid[@filepath="%s"]', $absoluteRomPath)
        );

        if (!$results) {
            return [];
        }

        $id = (string) $results[0]['id'];

        $xml = simplexml_load_file($dbPath);
        if (!$xml) {
            return [];
        }

        $results = $xml->xpath(
            sprintf('//resource[@id="%s"][@type="%s"]', $id, $resource)
        );

        if (!$results) {
            return [];
        }

        $imageFilePath = (string) $results[0];
        $fullImageFilePath = Path::join($cachePath, $platform, $imageFilePath);

        if (!$filesystem->exists($fullImageFilePath)) {
            return [];
        }

        return getimagesize($fullImageFilePath) ?: [];
    }

    public function getImageSizingHelperForRom(string $absoluteRomPath, string $platform, string $resource = 'screenshot'): ?ImageSizing
    {
        $size = $this->getImageSizeForRom($absoluteRomPath, $platform, $resource);
        if (empty($size) || !isset($size[0]) || !isset($size[1])) {
            return null;
        }

        return new ImageSizing($size[0], $size[1]);
    }
}
