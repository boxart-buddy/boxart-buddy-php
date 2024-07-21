<?php

namespace App\Util;

readonly class ImageSizing
{
    public function __construct(public int $width, public int $height)
    {
    }

    private function getWidthAndHeightToCover(int $width, int $height): array
    {
        // Calculate scale factors
        $scaleWidth = $width / $this->width;
        $scaleHeight = $height / $this->height;

        $scale = max($scaleWidth, $scaleHeight);

        $newWidth = ceil($scale * $this->width);
        $newHeight = ceil($scale * $this->height);

        return ['w' => $newWidth, 'h' => $newHeight];
    }

    // Alias for function as neater in template
    public function cover(int $width, int $height): array
    {
        return $this->getWidthAndHeightToCover($width, $height);
    }

    private function getWidthAndHeightToFit(int $width, int $height): array
    {
        // Calculate scale factors
        $scaleWidth = $width / $this->width;
        $scaleHeight = $height / $this->height;

        $scale = min($scaleWidth, $scaleHeight);

        $newWidth = ceil($scale * $this->width);
        $newHeight = ceil($scale * $this->height);

        return ['w' => $newWidth, 'h' => $newHeight];
    }

    // Alias for function as neater in template
    public function fit(int $width, int $height): array
    {
        return $this->getWidthAndHeightToFit($width, $height);
    }
}
