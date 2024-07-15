<?php

namespace App\Command\Transformer;

use App\Provider\ThemeProvider;

readonly class MakeConfigurationTransformer
{
    public function __construct(private ThemeProvider $themeProvider)
    {
    }

    // transform make data by merging theme specific options over the top
    public function transformForTheme(array $makeVariantData, string $themeName): array
    {
        $replacementData = $this->convertThemeDataToMakeShape(
            $themeName,
            $this->themeProvider->getThemeData($themeName),
            $makeVariantData
        );

        return array_replace_recursive($makeVariantData, $replacementData);
    }

    // takes theme data and transforms it so that it takes the same shape as 'make.yml' data so it can be merged
    private function convertThemeDataToMakeShape(
        string $themeName,
        array $themeData,
        array $makeData
    ): array {
        $converted = [];

        // append the theme name to the package name
        $converted['package_name'] = $makeData['package_name'].'-'.strtolower($themeName);

        if (isset($themeData['post_process'])) {
            foreach ($themeData['post_process'] as $themePostProcessData) {
                foreach ($this->getPostProcessMatchingIndexes($themePostProcessData['strategy'], $makeData) as $i) {
                    $converted['post_process'][$i] = $themePostProcessData;
                }
            }
        }

        return $converted;
    }

    private function getPostProcessMatchingIndexes(
        string $strategy,
        array $makeData
    ): array {
        $matching = [];
        if (!isset($makeData['post_process'])) {
            return $matching;
        }

        foreach ($makeData['post_process'] as $index => $d) {
            if ($d['strategy'] === $strategy) {
                $matching[] = $index;
            }
        }

        return $matching;
    }
}
