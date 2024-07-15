<?php

namespace App\Provider;

use App\Command\CommandNamespace;
use App\Config\Reader\ConfigReader;
use App\Skyscraper\RomExtensionProvider;
use App\Util\Path;
use Illuminate\Support\Str;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[WithMonologChannel('postprocessing')]
readonly class PackagedImagePathProvider
{
    public function __construct(
        private PathProvider $pathProvider,
        private PlatformProvider $platformProvider,
        private RomExtensionProvider $romExtensionProvider,
        private ConfigReader $configReader,
        private LoggerInterface $logger
    ) {
    }

    public function getPackagedImagePathsBySourceFolder(string $source, string $package, bool $includeFiles, bool $includeFolders): array
    {
        $platform = $this->platformProvider->getPlatformOrNull($source);

        $finder = new Finder();
        $finder->in($source)->depth(0)->files();
        if ($platform) {
            $this->romExtensionProvider->addRomExtensionsToFinder($finder, $platform);
        }

        // files
        $files = $includeFiles ? $this->getFilesByFinder($finder, CommandNamespace::ARTWORK, $package, $platform) : [];

        // folders
        $finder = new Finder();
        $finder->in($source)->depth(0)->directories();

        $folders = $includeFolders ? $this->getFilesByFinder($finder, CommandNamespace::FOLDER, $package, $platform) : [];

        return array_merge($files, $folders);
    }

    private function getFilesByFinder(Finder $finder, CommandNamespace $commandNamespace, string $package, ?string $platform): array
    {
        $files = [];
        $filesystem = new Filesystem();
        foreach ($finder as $info) {
            $asset = null;

            // this is sketchy
            if (Str::contains(strtolower($info->getRealPath()), '/ports') && !$info->isDir()) {
                $asset = Path::join(
                    $this->pathProvider->getPackageRootPath($package),
                    'MUOS',
                    'info',
                    'catalogue',
                    'External - Ports', 'box',
                    $info->getFilenameWithoutExtension().'.png'
                );
            }

            if (!$asset) {
                $asset = $this->getPackagedPathForAsset(
                    $info->getFilenameWithoutExtension().'.png',
                    $package,
                    $commandNamespace,
                    $platform,
                );
            }

            if (!$filesystem->exists($asset)) {
                $this->logger->warning(
                    sprintf('Asset missing during postprocessing, boxart will be absent for `%s`. Image expected was `%s`', $info->getFilename(), $asset)
                );
                continue;
            }

            $files[$info->getFilenameWithoutExtension().'.png'] = $asset;
        }

        return $files;
    }

    public function getPackagedPathForAsset(string $asset, string $package, CommandNamespace $namespace, ?string $platform): string
    {
        $base = Path::join(
            $this->pathProvider->getPackageRootPath($package),
            'MUOS',
            'info',
            'catalogue',
        );

        if (CommandNamespace::FOLDER === $namespace) {
            return Path::join($base, 'Folder', 'box', $asset);
        }

        if (!$platform) {
            throw new \RuntimeException(sprintf('Platform missing during post processing, Platform must be provided for non Folder/Portmaster items: %s', $asset));
        }

        // if (CommandNamespace::ARTWORK === $namespace)
        return Path::join(
            $base,
            $this->configReader->getConfig()->getPackageFolderForPlatform($platform),
            'box',
            $asset
        );
    }
}
