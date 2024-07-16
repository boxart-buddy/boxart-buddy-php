<?php

namespace App\Util;

use App\Config\Reader\ConfigReader;
use Symfony\Component\Filesystem\Filesystem;

readonly class SkyscraperResourceImageSizingHelper
{
    public function __construct(private ConfigReader $configReader)
    {
    }

    private function getWidthAndHeightToCover(int $width, int $height, int $resourceWidth, int $resourceHeight): array
    {
        // Calculate scale factors
        $scaleWidth = $width / $resourceWidth;
        $scaleHeight = $height / $resourceHeight;

        $scale = max($scaleWidth, $scaleHeight);

        $newWidth = ceil($scale * $resourceWidth);
        $newHeight = ceil($scale * $resourceHeight);

        return ['w' => $newWidth, 'h' => $newHeight];
    }

    private function getWidthAndHeightToFit(int $width, int $height, int $resourceWidth, int $resourceHeight): array
    {
        // Calculate scale factors
        $scaleWidth = $width / $resourceWidth;
        $scaleHeight = $height / $resourceHeight;

        $scale = min($scaleWidth, $scaleHeight);

        $newWidth = ceil($scale * $resourceWidth);
        $newHeight = ceil($scale * $resourceHeight);

        return ['w' => $newWidth, 'h' => $newHeight];
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

        if (!$size || !isset($size[0]) || !isset($size[1])) {
            return $fallback;
        }

        return $this->getWidthAndHeightToCover($width, $height, $size[0], $size[1]);
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

        if (!$size || !isset($size[0]) || !isset($size[1])) {
            return $fallback;
        }

        return $this->getWidthAndHeightToFit($width, $height, $size[0], $size[1]);
    }
}
