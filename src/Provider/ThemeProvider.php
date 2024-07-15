<?php

namespace App\Provider;

use App\Util\Finder;
use App\Util\Path;
use Symfony\Component\Yaml\Yaml;

readonly class ThemeProvider
{
    public function __construct(private Path $path)
    {
    }

    public function getThemes(): array
    {
        $themes = [];

        foreach ($this->getThemeFiles() as $themeFile) {
            $themes[] = $themeFile->getFilenameWithoutExtension();
        }

        return $themes;
    }

    private function getThemeFiles(): Finder
    {
        $finder = new Finder();
        $finder->in($this->path->joinWithBase('resources', 'theme-defaults'));
        $finder->files()->name('*.yml');

        return $finder;
    }

    public function getThemeData(string $name): array
    {
        $finder = new Finder();
        $finder->in($this->path->joinWithBase('resources', 'theme-defaults'));
        $finder->files()->name($name.'.yml');

        if (!$finder->hasResults()) {
            throw new \InvalidArgumentException(sprintf('Cannot get data for unknown theme %s', $name));
        }

        return Yaml::parseFile($finder->first());
    }
}
