<?php

namespace App\Command\Factory;

use App\ApplicationConstant;
use App\Command\GenerateEmptyImageCommand;
use App\Command\GenerateFolderArtworkCommand;
use App\Command\GenerateRomArtworkCommand;
use App\Command\PostProcessCommand;
use App\Command\PreviewCommand;
use App\Command\PrimeCacheCommand;
use App\Config\Reader\ConfigReader;
use App\Provider\PathProvider;
use App\Provider\PlatformProvider;
use App\Skyscraper\RomExtensionProvider;
use App\Util\Path;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

readonly class CommandFactory
{
    public function __construct(
        private ConfigReader $configReader,
        private PathProvider $pathProvider,
        private RomExtensionProvider $romExtensionProvider,
        private LoggerInterface $logger,
        private PlatformProvider $platformProvider
    ) {
    }

    public function createPostProcessCommands(string $package, string $strategy, array $options, bool $files, bool $folders): array
    {
        $commands = [];
        $config = $this->configReader->getConfig();

        $commands[] = new PostProcessCommand($config->romFolder, $package, $strategy, $options, $files, $folders);

        $finder = new Finder();
        $finder->in($config->romFolder)->directories();

        foreach ($finder as $source) {
            // only postprocess if there is a platform definition for the folder
            if (null !== $this->platformProvider->getPlatformOrNull($source->getRealPath())) {
                $commands[] = new PostProcessCommand($source->getRealPath(), $package, $strategy, $options, $files, $folders);
            }
        }

        return $commands;
    }

    public function createPostProcessCommandForPortmaster(string $package, string $strategy, array $options): PostProcessCommand
    {
        return new PostProcessCommand($this->pathProvider->getPortmasterRomPath(), $package, $strategy, $options, true, false);
    }

    public function createGeneratePreviewCommands(string $package, string $previewName): array
    {
        $target = $this->pathProvider->getPackageRootPath($package);

        $commands = [];

        $commands[] = new PreviewCommand($target, $previewName, $this->configReader->getConfig()->previewType);

        return $commands;
    }

    public function createGenerateArtworkCommandForPortmaster(
        string $artworkPackage,
        string $artworkFilename,
        array $tokens
    ): array {
        $portmasterAlternates = $this->configReader->getConfig()->portmasterAlternates;

        $commands = [];

        $finder = new Finder();
        $finder->in($this->pathProvider->getPortmasterRomPath())->files();

        foreach ($finder as $file) {
            $gameName = $file->getFilenameWithoutExtension();

            // change platform if alternate exists
            $platform = $portmasterAlternates[$gameName]['platform'] ?? ApplicationConstant::FAKE_PORTMASTER_PLATFORM;

            $commands[] = new GenerateRomArtworkCommand(
                $artworkPackage,
                $artworkFilename,
                $tokens,
                $file->getRealPath(),
                false,
                $platform,
                true
            );
        }

        return $commands;
    }

    public function createPrimeCacheCommands(bool $onlyMissing): array
    {
        $commands = [];
        $config = $this->configReader->getConfig();
        foreach ($config->folders as $folder => $platform) {
            if (ApplicationConstant::FAKE_PORTMASTER_PLATFORM === $platform) {
                continue;
            }
            $folderAbsolutePath = Path::join($config->romFolder, $folder);
            $commands[] = new PrimeCacheCommand($folderAbsolutePath, $onlyMissing);
        }

        return $commands;
    }

    public function createEmptyImageCommands(): array
    {
        return $this->getGenerateEmptyImageCommandsForFolder($this->configReader->getConfig()->romFolder, true);
    }

    public function getGenerateEmptyImageCommandsForFolder(string $folder, bool $traverseSubdirectories): array
    {
        $commands = [];

        $platform = $this->platformProvider->getPlatformOrNull($folder);

        if (null === $platform) {
            $this->logger->notice(sprintf('No platform provided for folder `%s`, skipping', $folder));
        }

        if ($platform) {
            // do roms first
            $finder = new Finder();
            $finder->in($folder)->files()->depth('== 0');

            $this->romExtensionProvider->addRomExtensionsToFinder($finder, $platform);

            foreach ($finder as $file) {
                $commands[] = new GenerateEmptyImageCommand($file->getRealPath(), $platform);
            }
        }

        // do folders next
        $finder = new Finder();
        $finder->in($folder)->directories()->depth('== 0');
        foreach ($finder as $directory) {
            $platform = $this->platformProvider->getPlatformOrNull($directory);
            if ($platform) {
                $commands[] = new GenerateEmptyImageCommand($directory->getRealPath(), $platform);
            }

            if ($traverseSubdirectories) {
                $commands = array_merge(
                    $commands,
                    $this->getGenerateEmptyImageCommandsForFolder(
                        $directory->getRealPath(),
                        true
                    )
                );
            }
        }

        return $commands;
    }

    public function createGenerateArtworkCommands(
        string $artworkPackage,
        ?string $artworkFilename,
        string $folderPackage,
        ?string $folderFilename,
        array $tokens,
    ): array {
        return $this->getGenerateArtworkCommandsForFolder(
            $this->configReader->getConfig()->romFolder,
            $artworkPackage,
            $artworkFilename,
            $folderPackage,
            $folderFilename,
            $tokens,
            true,
            true
        );
    }

    public function getGenerateArtworkCommandsForFolder(
        string $folder,
        string $artworkPackage,
        ?string $artworkFilename,
        string $folderPackage,
        ?string $folderFilename,
        array $tokens,
        bool $generateDescriptions,
        bool $traverseSubdirectories
    ): array {
        $commands = [];

        // skip hidden folders
        if (in_array(basename($folder), RomExtensionProvider::getDirectoryExcludes())) {
            return $commands;
        }

        $platform = $this->platformProvider->getPlatformOrNull($folder);

        if (null === $platform) {
            $this->logger->notice(sprintf('No platform provided for folder `%s`, skipping', $folder));
        }

        if ($platform && $artworkFilename) {
            // do roms first
            $finder = new Finder();
            $finder->in($folder)->files()->depth('== 0');

            $this->romExtensionProvider->addRomExtensionsToFinder($finder, $platform);

            foreach ($finder as $file) {
                $commands[] = new GenerateRomArtworkCommand(
                    $artworkPackage,
                    $artworkFilename,
                    $tokens,
                    $file->getRealPath(),
                    $generateDescriptions,
                    null,
                    false
                );
            }
        }

        // do folders next
        $finder = new Finder();
        $finder->in($folder)->directories()->depth('== 0');
        foreach ($finder as $directory) {
            $platform = $this->platformProvider->getPlatformOrNull($directory);
            if ($platform && $folderFilename) {
                $commands[] = new GenerateFolderArtworkCommand(
                    $folderPackage,
                    $folderFilename,
                    $tokens,
                    $directory->getRealPath()
                );
            }

            if ($traverseSubdirectories) {
                $commands = array_merge(
                    $commands,
                    $this->getGenerateArtworkCommandsForFolder(
                        $directory->getRealPath(),
                        $artworkPackage,
                        $artworkFilename,
                        $folderPackage,
                        $folderFilename,
                        $tokens,
                        $generateDescriptions,
                        true
                    )
                );
            }
        }

        return $commands;
    }
}
