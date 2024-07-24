<?php

namespace App\Provider;

use App\Command\CommandNamespace;
use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Util\Path;

readonly class PathProvider
{
    public function __construct(
        private Path $path,
        private ConfigReader $configReader
    ) {
    }

    public function removeRomFolderBase(string $folderAbsolutePath): string
    {
        return Path::remove($folderAbsolutePath, $this->configReader->getConfig()->romFolder);
    }

    public function removeOutputPathBase(string $folderAbsolutePath): string
    {
        return Path::remove($folderAbsolutePath, $this->getOutputPathBase());
    }

    public function getOutputPathForGeneratedArtwork(string $folder): string
    {
        return Path::join($this->getOutputPathBase(), $folder);
    }

    public function getOutputPathForGeneratedArtworkForNamespace(string $romAbsolutePath, CommandNamespace $namespace): string
    {
        return match ($namespace) {
            CommandNamespace::ARTWORK => $this->getOutputPathForGeneratedArtwork($this->removeRomFolderBase(dirname($romAbsolutePath))),
            CommandNamespace::FOLDER => $this->getOutputPathForGeneratedArtwork('Folder'),
            CommandNamespace::PORTMASTER => $this->getOutputPathForGeneratedArtwork('Ports')
        };
    }

    private function getOutputPathBase(): string
    {
        return $this->path->joinWithBase(
            FolderNames::TEMP->value,
            'output',
            'generated_artwork'
        );
    }

    public function getGamelistPath(string $folder): string
    {
        return $this->path->joinWithBase(
            FolderNames::TEMP->value,
            'output',
            'gamelist',
            $folder
        );
    }

    public function getPortmasterRomPath(): string
    {
        return $this->path->joinWithBase(FolderNames::TEMP->value, 'portmaster', 'roms');
    }

    public function getFakeRomPath(): string
    {
        return $this->path->joinWithBase(FolderNames::TEMP->value, 'fake_roms', 'roms');
    }

    public function getPackageRootPath(string $packageName): string
    {
        $romsetName = $this->configReader->getConfig()->romsetName;

        return $this->path->joinWithBase(FolderNames::PACKAGE->value, $packageName.'-'.$romsetName);
    }

    public function getPackageZipPath(string $packageName): string
    {
        $romsetName = $this->configReader->getConfig()->romsetName;

        return $this->path->joinWithBase(FolderNames::ZIPPED->value, $packageName.'-'.$romsetName.'.zip');
    }

    public function getFontPath(string $family, ?string $variant = null): string
    {
        if ('vag_rounded' === $family) {
            return $this->path->joinWithBase('resources', 'font', 'vag-rounded', 'VAG-Rounded-Bold.ttf');
        }

        if ('cousine' === $family) {
            return match ($variant) {
                'bold' => $this->path->joinWithBase('resources', 'font', 'cousine', 'Cousine-Bold.ttf'),
                'italic' => $this->path->joinWithBase('resources', 'font', 'cousine', 'Cousine-Italic.ttf'),
                'bold-italic' => $this->path->joinWithBase('resources', 'cousine', 'font', 'Cousine-BoldItalic.ttf'),
                default => $this->path->joinWithBase('resources', 'font', 'cousine', 'Cousine-Regular.ttf')
            };
        }

        if ('lucida-grande' === $family) {
            return match ($variant) {
                'bold' => $this->path->joinWithBase('resources', 'font', 'lucida-grande', 'bold.ttf'),
                'regular' => $this->path->joinWithBase('resources', 'font', 'lucida-grande', 'regular.ttf'),
                default => $this->path->joinWithBase('resources', 'font', 'lucida-grande', 'regular.ttf')
            };
        }

        if ('roboto' === $family) {
            return match ($variant) {
                'black' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Black.ttf'),
                'black-italic' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-BlackItalic.ttf'),
                'bold' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Bold.ttf'),
                'bold-italic' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-BoldItalic.ttf'),
                'italic' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Italic.ttf'),
                'light' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Light.ttf'),
                'light-italic' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-LightItalic.ttf'),
                'medium' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Medium.ttf'),
                'medium-italic' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-MediumItalic.ttf'),
                'regular' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Regular.ttf'),
                'thin' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Thin.ttf'),
                'thin-italic' => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-ThinItalic.ttf'),
                default => $this->path->joinWithBase('resources', 'font', 'roboto', 'Roboto-Regular.ttf'),
            };
        }
        if ('bariol' === $family) {
            return match ($variant) {
                'bold' => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_bold-webfont.ttf'),
                'bold-italic' => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_bold_italic-webfont.ttf'),
                'light' => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_light-webfont.ttf'),
                'light-italic' => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_light_italic-webfont.ttf'),
                'regular-italic' => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_regular_italic-webfont.ttf'),
                'thin' => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_thin-webfont.ttf'),
                'thin-italic' => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_thin_italic-webfont.ttf'),
                default => $this->path->joinWithBase('resources', 'font', 'bariol', 'bariol_thin_italic-webfont.ttf'),
            };
        }

        if ('pixel' === $family) {
            return match ($variant) {
                'AKDPixel' => $this->path->joinWithBase('resources', 'font', 'pixel', 'AKDPixel.ttf'),
                'AtariGames' => $this->path->joinWithBase('resources', 'font', 'pixel', 'AtariGames.ttf'),
                'Awexbmp' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Awexbmp.ttf'),
                'BIOSfontII' => $this->path->joinWithBase('resources', 'font', 'pixel', 'BIOSfontII.ttf'),
                'BasicChineseLine' => $this->path->joinWithBase('resources', 'font', 'pixel', 'BasicChineseLine.ttf'),
                'Beanstalk' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Beanstalk.ttf'),
                'Bitfantasy' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Bitfantasy.ttf'),
                'CelticTime' => $this->path->joinWithBase('resources', 'font', 'pixel', 'CelticTime.ttf'),
                'ClassicShit' => $this->path->joinWithBase('resources', 'font', 'pixel', 'ClassicShit.ttf'),
                'DisrespectfulTeenager' => $this->path->joinWithBase('resources', 'font', 'pixel', 'DisrespectfulTeenager.ttf'),
                'GTA2PSX' => $this->path->joinWithBase('resources', 'font', 'pixel', 'GTA2PSX.ttf'),
                'Habbo' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Habbo.ttf'),
                'KarenFat' => $this->path->joinWithBase('resources', 'font', 'pixel', 'KarenFat.ttf'),
                'Khonjin' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Khonjin.ttf'),
                'Kubasta' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Kubasta.ttf'),
                'LCDBlock' => $this->path->joinWithBase('resources', 'font', 'pixel', 'LCDBlock.ttf'),
                'LessRoundBox' => $this->path->joinWithBase('resources', 'font', 'pixel', 'LessRoundBox.ttf'),
                'LowIndustrial' => $this->path->joinWithBase('resources', 'font', 'pixel', 'LowIndustrial.ttf'),
                'MMXSNES' => $this->path->joinWithBase('resources', 'font', 'pixel', 'MMXSNES.ttf'),
                'MyHandwriting' => $this->path->joinWithBase('resources', 'font', 'pixel', 'MyHandwriting.ttf'),
                'NameHereCondensed' => $this->path->joinWithBase('resources', 'font', 'pixel', 'NameHereCondensed.ttf'),
                'PixNull' => $this->path->joinWithBase('resources', 'font', 'pixel', 'PixNull.ttf'),
                'PixelNewspaperIII' => $this->path->joinWithBase('resources', 'font', 'pixel', 'PixelNewspaperIII.ttf'),
                'Rockboxcond12' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Rockboxcond12.ttf'),
                'SandyForest' => $this->path->joinWithBase('resources', 'font', 'pixel', 'SandyForest.ttf'),
                'SquareSounds' => $this->path->joinWithBase('resources', 'font', 'pixel', 'SquareSounds.ttf'),
                'SuperTechnology' => $this->path->joinWithBase('resources', 'font', 'pixel', 'SuperTechnology.ttf'),
                'TWEENIESDODDLEBINES' => $this->path->joinWithBase('resources', 'font', 'pixel', 'TWEENIESDODDLEBINES.ttf'),
                'Tallpix' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Tallpix.ttf'),
                'ThickPixels' => $this->path->joinWithBase('resources', 'font', 'pixel', 'ThickPixels.ttf'),
                'TinyPixie2' => $this->path->joinWithBase('resources', 'font', 'pixel', 'TinyPixie2.ttf'),
                'TinyUnicode' => $this->path->joinWithBase('resources', 'font', 'pixel', 'TinyUnicode.ttf'),
                'TripleN' => $this->path->joinWithBase('resources', 'font', 'pixel', 'TripleN.ttf'),
                'Unknown' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Unknown.ttf'),
                'Zicons' => $this->path->joinWithBase('resources', 'font', 'pixel', 'Zicons.ttf'),
                'c64esque' => $this->path->joinWithBase('resources', 'font', 'pixel', 'c64esque.ttf'),
                'daryloo' => $this->path->joinWithBase('resources', 'font', 'pixel', 'daryloo.ttf'),
                'fude' => $this->path->joinWithBase('resources', 'font', 'pixel', 'fude.ttf'),
                'prevoard' => $this->path->joinWithBase('resources', 'font', 'pixel', 'prevoard.ttf'),
                'scribble1' => $this->path->joinWithBase('resources', 'font', 'pixel', 'scribble1.ttf'),
                'DiaryOfAn8BitMage' => $this->path->joinWithBase('resources', 'font', 'pixel', 'DiaryOfAn8BitMage.ttf'),
                default => $this->path->joinWithBase('resources', 'font', 'pixel', 'scribble1.ttf'),
            };
        }

        throw new \InvalidArgumentException(sprintf('Cannot get font for unknown font family/variant: %s/%s', $family, $variant));
    }

    public function getRandomFontPath(): string
    {
        $fonts = ['AtariGames', 'CelticTime', 'MMXSNES', 'KarenFat', 'ClassicShit', 'PixelNewspaperIII', 'Rockboxcond12'];
        $family = $fonts[array_rand($fonts)];

        return $this->getFontPath('pixel', $family);
    }

    public function getLogPath(string $namespace): string
    {
        return $this->path->joinWithBase('var', 'log', sprintf('%s-%s.log', $namespace, date('Y-m-d')));
    }
}
