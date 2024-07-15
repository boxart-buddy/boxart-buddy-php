<?php

namespace App\Command\Handler;

use App\Command\CommandInterface;
use App\Command\GenerateFolderArtworkCommand;
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
        private PlatformProvider $platformProvider
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof GenerateFolderArtworkCommand) {
            throw new \InvalidArgumentException();
        }

        // @todo does there need to be a way of generating artwork without artwork.xml?
        // What about folders without any roms in them
        // Should this allow null to skip artwork generation? (Not every template is sibling rememember)

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
