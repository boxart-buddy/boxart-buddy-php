<?php

namespace App\Command\Handler;

use App\Command\CommandInterface;
use App\Command\GenerateFolderArtworkCommand;
use App\Config\Reader\ConfigReader;
use App\Generator\ArtworkGenerator;
use App\Provider\ArtworkProvider;
use App\Provider\FolderRomProvider;
use App\Provider\PathProvider;
use App\Provider\PlatformProvider;

readonly class GenerateFolderArtworkHandler implements CommandHandlerInterface
{
    public function __construct(
        private ArtworkGenerator $artworkGenerator,
        private ArtworkProvider $artworkProvider,
        private PathProvider $pathProvider,
        private FolderRomProvider $folderRomProvider,
        private PlatformProvider $platformProvider,
        private ConfigReader $configReader
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof GenerateFolderArtworkCommand) {
            throw new \InvalidArgumentException();
        }

        $artwork = $this->artworkProvider->getArtwork($command->artworkPackage, $command->artwork);

        $platform = $this->platformProvider->getPlatform($command->folderAbsolutePath);

        $romAbsolutePath = $this->folderRomProvider->getSingleRomByFolder($command->folderAbsolutePath);

        if (!$romAbsolutePath) {
            return;
        }

        $this->artworkGenerator->generateFolderArtwork(
            $artwork,
            $platform,
            $command->tokens,
            $romAbsolutePath,
            $this->pathProvider->removeRomFolderBase($command->folderAbsolutePath)
        );
    }
}
