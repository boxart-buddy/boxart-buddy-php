<?php

namespace App\PostProcess;

use App\PostProcess\Option\CounterPostProcessOptions;
use App\Provider\PathProvider;
use Intervention\Image\Interfaces\FontInterface;
use Intervention\Image\Typography\Font;
use Intervention\Image\Typography\FontFactory;

trait FontTrait
{
    /**
     * @throws \ImagickException
     * @throws \ImagickDrawException
     */
    protected function getFontMetrics(array $options, PathProvider $pathProvider, ?float $textSize = null, string $text = '8'): array
    {
        $cacheKey = hash('xxh3', serialize($options).$textSize.$text);

        if (isset($this->fontMetricCache) && array_key_exists($cacheKey, $this->fontMetricCache)) {
            return $this->fontMetricCache[$cacheKey];
        }

        $fontFamily = $options[CounterPostProcessOptions::TEXT_FONT_FAMILY];
        $fontVariant = $options[CounterPostProcessOptions::TEXT_FONT_VARIANT];

        $fontPath = $pathProvider->getFontPath($fontFamily, $fontVariant);

        $im = new \Imagick();
        $draw = new \ImagickDraw();
        $draw->setFont($fontPath);
        if ($textSize) {
            $draw->setFontSize($textSize);
        }

        $metrics = $im->queryFontMetrics($draw, $text);

        if (isset($this->fontMetricCache)) {
            $this->fontMetricCache[$cacheKey] = $metrics;
        }

        return $metrics;
    }

    protected function getFont(string $fontPath, int $fontSize, string $textColor, mixed $textHAlign, mixed $textVAlign, ?int $wrap = null): FontInterface
    {
        $cacheKey = hash('xxh3', $fontPath.$textColor.$textHAlign.$wrap.$fontSize);

        if (isset($this->fontCache) && array_key_exists($cacheKey, $this->fontCache)) {
            return $this->fontCache[$cacheKey];
        }

        $factory = new FontFactory(new Font());
        $factory->filename($fontPath);
        $factory->size($fontSize);
        $factory->color($textColor);
        if ($textHAlign) {
            $factory->align($textHAlign);
        }
        if ($textVAlign) {
            $factory->valign($textVAlign);
        }
        $factory->lineHeight(1.9);
        if ($wrap) {
            $factory->wrap($wrap);
        }

        $font = $factory();

        if (isset($this->fontCache)) {
            $this->fontCache[$cacheKey] = $font;
        }

        return $font;
    }
}
