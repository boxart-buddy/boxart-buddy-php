<?php

namespace App\Skyscraper;

use App\Config\Reader\ConfigReader;
use App\Util\ImageSizing;
use App\Util\Path;
use Intervention\Image\ImageManager;
use Symfony\Component\Filesystem\Filesystem;

class CacheReader
{
    private array $xmlCache;

    public function __construct(
        readonly private ConfigReader $configReader
    ) {
        $this->xmlCache = [];
    }

    private function getXml(string $path): ?\SimpleXMLElement
    {
        $cacheKey = sprintf('%s-%s', basename(dirname($path)), Path::removeExtension(basename($path)));
        if (array_key_exists($cacheKey, $this->xmlCache)) {
            return $this->xmlCache[$cacheKey];
        }

        $this->xmlCache[$cacheKey] = simplexml_load_file($path);

        return $this->xmlCache[$cacheKey];
    }

    public function getImageSizingHelperForRom(string $absoluteRomPath, string $platform, string $resource): ?ImageSizing
    {
        $filesystem = new Filesystem();

        $cachePath = $this->configReader->getConfig()->skyscraperCacheFolderPath;
        $quickIdPath = Path::join($cachePath, $platform, 'quickid.xml');
        $dbPath = Path::join($cachePath, $platform, 'db.xml');

        if (!$filesystem->exists($dbPath) || !$filesystem->exists($quickIdPath)) {
            return null;
        }

        $xml = $this->getXml($quickIdPath);
        if (!$xml) {
            return null;
        }

        $results = $xml->xpath(
            sprintf('//quickid[@filepath="%s"]', $absoluteRomPath)
        );

        if (!$results) {
            return null;
        }

        $id = (string) $results[0]['id'];

        $xml = $this->getXml($dbPath);
        if (!$xml) {
            return null;
        }

        $results = $xml->xpath(
            sprintf('//resource[@id="%s"][@type="%s"]', $id, $resource)
        );

        if (!$results) {
            return null;
        }

        $imageFilePath = (string) $results[0];
        $fullImageFilePath = Path::join($cachePath, $platform, $imageFilePath);

        if (!$filesystem->exists($fullImageFilePath)) {
            return null;
        }

        $image = ImageManager::imagick()->read($fullImageFilePath);
        $i = $image->core()->native();
        $i->trimImage(10);
        $i->setImagePage(0, 0, 0, 0);

        return new ImageSizing($image->width(), $image->height());
    }
}
