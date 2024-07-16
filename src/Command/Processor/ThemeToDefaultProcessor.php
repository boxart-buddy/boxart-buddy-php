<?php

namespace App\Command\Processor;

use App\FolderNames;
use App\Util\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

readonly class ThemeToDefaultProcessor
{
    public function __construct(private Path $path)
    {
    }

    public function process(): void
    {
        $finder = new Finder();
        $finder->in($this->path->joinWithBase(FolderNames::THEME->value));

        $finder->directories()->depth(0);

        foreach ($finder as $folder) {
            $this->processTheme($folder->getRealPath());
        }
    }

    private function processTheme(string $themePath): void
    {
        $filesystem = new Filesystem();
        $themeFolderName = basename($themePath);
        $themePrettyName = Path::prettifyFilename($themeFolderName);

        $filePath = $this->path->joinWithBase(
            'resources',
            'theme-defaults',
            $themePrettyName.'.yml'
        );

        if (!$filesystem->exists($filePath)) {
            $filesystem->touch($filePath);
        }

        // $existing = Yaml::parseFile($filePath) ?? [];
        $new = $this->getDefaultDataForTheme($themeFolderName, $themePrettyName);

        // $merged = array_replace_recursive($existing, $new);
        $filesystem->dumpFile($filePath, Yaml::dump($new, 2, 2));
    }

    private function getDefaultDataForTheme(string $themeFolder, string $themePrettyName): array
    {
        $font = $this->getFontInfoByTheme($themePrettyName);
        $new = [];

        $textColor = $this->readProperty($themeFolder, 'LIST_DEFAULT_TEXT');
        $textAltColor = $this->readProperty($themeFolder, 'LIST_FOCUS_TEXT');
        $bgColor = $this->readProperty($themeFolder, 'LIST_FOCUS_BACKGROUND');

        $strategyCounter = 0;

        if ($textColor) {
            $new['post_process'][$strategyCounter]['strategy'] = 'counter';
            $new['post_process'][$strategyCounter]['color'] = $textColor;
            if ($font) {
                $new['post_process'][$strategyCounter]['font_family'] = $font['family'];
                $new['post_process'][$strategyCounter]['font_variant'] = $font['variant'];
            }
            ++$strategyCounter;
        }

        if ($textColor) {
            $new['post_process'][$strategyCounter]['strategy'] = 'translation';
            $new['post_process'][$strategyCounter]['color'] = $textColor;
            if ($font) {
                $new['post_process'][$strategyCounter]['font_family'] = $font['family'];
                $new['post_process'][$strategyCounter]['font_variant'] = $font['variant'];
            }
            ++$strategyCounter;
        }

        $copyBackground = $this->copyBackground($themeFolder, $themePrettyName);
        if ($copyBackground) {
            $new['post_process'][$strategyCounter]['strategy'] = 'background';
            $new['post_process'][$strategyCounter]['background_default'] = sprintf('theme-background/%s.png', $themePrettyName);
            ++$strategyCounter;
        }

        if ($textColor && $bgColor) {
            $new['post_process'][$strategyCounter]['strategy'] = 'vertical_scrollbar';
            $new['post_process'][$strategyCounter]['track_color'] = $textColor;
            $new['post_process'][$strategyCounter]['thumb_color'] = $bgColor;
            ++$strategyCounter;
        }

        if ($textColor && $bgColor) {
            $new['post_process'][$strategyCounter]['strategy'] = 'text';
            $new['post_process'][$strategyCounter]['color'] = $textColor;
            $new['post_process'][$strategyCounter]['color_alt'] = $textAltColor;
            $new['post_process'][$strategyCounter]['background_color'] = $bgColor;
            if ($font) {
                $new['post_process'][$strategyCounter]['font_family'] = $font['family'];
                $new['post_process'][$strategyCounter]['font_variant'] = $font['variant'];
            }
            ++$strategyCounter;
        }

        if ($textColor) {
            $new['post_process'][$strategyCounter]['strategy'] = 'vertical_dot_scrollbar';
            $new['post_process'][$strategyCounter]['dotcolor'] = $textColor;
            ++$strategyCounter;
        }

        return $new;
    }

    private function readProperty(string $themeFolder, string $name): ?string
    {
        $filesystem = new Filesystem();
        $filepath = $this->path->joinWithBase(FolderNames::THEME->value, $themeFolder, 'scheme', 'default.txt');

        if (!$filesystem->exists($filepath)) {
            return null;
        }

        try {
            $default = parse_ini_file($filepath);
            if (!$default) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        if (!array_key_exists($name, $default)) {
            return null;
        }

        return $default[$name];
    }

    private function copyBackground(string $themeFolder, string $name): bool
    {
        $filesystem = new Filesystem();
        $bgPath = $this->path->joinWithBase(FolderNames::THEME->value, $themeFolder, 'image', 'wall', 'default.png');
        if (!$filesystem->exists($bgPath)) {
            return false;
        }

        $resourcePath = $this->path->joinWithBase('resources', 'common', 'post-process', 'theme-background', $name.'.png');

        $filesystem->copy($bgPath, $resourcePath, true);

        return true;
    }

    private function getFontInfoByTheme(string $theme): ?array
    {
        return match ($theme) {
            'gbos-color-amber', 'gbos-color-black', 'gbos-color-diamond', 'gbos-color-dmg', 'gbos-color-obsidian', 'gbos-color-ruby', 'gbos-color-sapphire', 'gbos-color-white', 'gbos-minimal', 'gbos', 'gbo' => ['family' => 'pixel', 'variant' => 'DiaryOfAn8BitMage'],
            'dot-matrix-green','dot-matrix-dark', 'dot-matrix-mustard', 'dot-matrix-pink' => ['family' => 'pixel', 'variant' => 'c64esque'],
            'gamepal-lcdlight','gamepal-lcdnight','gamepal-light','gamepal-night','gamepal-sneslight','gamepal-snesnight','gamepal-sober' => ['family' => 'bariol', 'variant' => 'bold'],
            'minuified-horizontal', 'minuified-vertical' => ['family' => 'vag_rounded', 'variant' => 'bold'],
            'muos-x-mountain-lion' => ['family' => 'lucidia-grande', 'variant' => 'regular'],
            default => null
        };
    }
}
