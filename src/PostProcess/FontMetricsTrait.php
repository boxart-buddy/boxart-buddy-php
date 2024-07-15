<?php

namespace App\PostProcess;

use App\PostProcess\Option\CounterPostProcessOptions;
use App\Provider\PathProvider;

trait FontMetricsTrait
{
    /**
     * @throws \ImagickException
     * @throws \ImagickDrawException
     */
    private function getFontMetrics(array $options, PathProvider $pathProvider, ?float $textSize = null, string $text = '8'): array
    {
        $fontFamily = $options[CounterPostProcessOptions::TEXT_FONT_FAMILY];
        $fontVariant = $options[CounterPostProcessOptions::TEXT_FONT_VARIANT];

        $fontPath = $pathProvider->getFontPath($fontFamily, $fontVariant);

        $im = new \Imagick();
        $draw = new \ImagickDraw();
        $draw->setFont($fontPath);
        if ($textSize) {
            $draw->setFontSize($textSize);
        }

        return $im->queryFontMetrics($draw, $text);
    }
}
