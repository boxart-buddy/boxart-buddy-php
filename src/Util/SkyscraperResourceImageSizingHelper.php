<?php

namespace App\Util;

use App\Config\Reader\ConfigReader;
use Intervention\Image\ImageManager;
use Symfony\Component\Filesystem\Filesystem;

readonly class SkyscraperResourceImageSizingHelper
{
    public function __construct(private ConfigReader $configReader)
    {
    }

    // Alias for function as neater in template
    public function cover(string $relativeResourcePath, int $width, int $height): array
    {
        $filesystem = new Filesystem();
        $absoluteResourcePath = Path::join(
            $this->configReader->getConfig()->skyscraperConfigFolderPath,
            'resources',
            $relativeResourcePath
        );

        $fallback = [
            'w' => $width,
            'h' => $height,
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

    public function fit(string $relativeResourcePath, int $width, int $height): array
    {
        $filesystem = new Filesystem();
        $absoluteResourcePath = Path::join(
            $this->configReader->getConfig()->skyscraperConfigFolderPath,
            'resources',
            $relativeResourcePath
        );

        $fallback = [
            'w' => $width,
            'h' => $height,
        ];

        if (!$filesystem->exists($absoluteResourcePath)) {
            return $fallback;
        }

        $image = ImageManager::imagick()->read($absoluteResourcePath);
        $i = $image->core()->native();
        $i->trimImage(10);
        $i->setImagePage(0, 0, 0, 0);

        $sizing = new ImageSizing($image->width(), $image->height());

        return $sizing->fit($width, $height);
    }
}
