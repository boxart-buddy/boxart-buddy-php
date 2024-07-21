<?php

namespace App\Util;

use App\Config\Reader\ConfigReader;
use Symfony\Component\Filesystem\Filesystem;

readonly class SkyscraperResourceImageSizingHelper
{
    public function __construct(private ConfigReader $configReader)
    {
    }

    // Alias for function as neater in template
    public function cover(string $relativeResourcePath, int $width, int $height, int $fallbackWidth, int $fallbackHeight): array
    {
        $filesystem = new Filesystem();
        $absoluteResourcePath = Path::join(
            $this->configReader->getConfig()->skyscraperConfigFolderPath,
            'resources',
            $relativeResourcePath
        );

        $fallback = [
            'w' => $fallbackWidth,
            'h' => $fallbackHeight,
        ];

        if (!$filesystem->exists($absoluteResourcePath)) {
            return $fallback;
        }

        $size = getimagesize($absoluteResourcePath);

        if (!$size) {
            return $fallback;
        }

        $sizing = new ImageSizing($size[0], $size[1]);

        return $sizing->cover($width, $height);
    }

    public function fit(string $relativeResourcePath, int $width, int $height, int $fallbackWidth, int $fallbackHeight): array
    {
        $filesystem = new Filesystem();
        $absoluteResourcePath = Path::join(
            $this->configReader->getConfig()->skyscraperConfigFolderPath,
            'resources',
            $relativeResourcePath
        );

        $fallback = [
            'w' => $fallbackWidth,
            'h' => $fallbackHeight,
        ];

        if (!$filesystem->exists($absoluteResourcePath)) {
            return $fallback;
        }

        $size = getimagesize($absoluteResourcePath);

        if (!$size) {
            return $fallback;
        }

        $sizing = new ImageSizing($size[0], $size[1]);

        return $sizing->fit($width, $height);
    }
}
