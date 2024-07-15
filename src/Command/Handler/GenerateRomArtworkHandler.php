<?php

namespace App\Command\Handler;

use App\ApplicationConstant;
use App\Command\CommandInterface;
use App\Command\GenerateRomArtworkCommand;
use App\Config\Reader\ConfigReader;
use App\Generator\ArtworkGenerator;
use App\Provider\ArtworkProvider;
use App\Provider\PlatformProvider;

readonly class GenerateRomArtworkHandler implements CommandHandlerInterface
{
    public function __construct(
        private ArtworkGenerator $artworkGenerator,
        private ArtworkProvider $artworkProvider,
        private PlatformProvider $platformProvider,
        private ConfigReader $configReader
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof GenerateRomArtworkCommand) {
            throw new \InvalidArgumentException();
        }

        $artwork = $this->artworkProvider->getArtwork($command->artworkPackage, $command->artwork);
        $forcePortmaster = $command->forcePortmaster;

        $platform = $command->platform;
        if (!$platform) {
            $platform = $this->platformProvider->getPlatform($command->romAbsolutePath);
        }

        // attempt to use portmaster alternates if this is a portmaster game
        if ('sh' === pathinfo($command->romAbsolutePath, PATHINFO_EXTENSION) && ApplicationConstant::FAKE_PORTMASTER_PLATFORM === $platform) {
            $alternatePlatform = $this->configReader->getConfig()->getPortmasterAlternatePlatform(basename($command->romAbsolutePath, '.sh'));
            if ($alternatePlatform) {
                $platform = $alternatePlatform;
            }
        }

        $this->artworkGenerator->generateRomArtwork(
            $artwork,
            $platform,
            $command->tokens,
            $command->romAbsolutePath,
            $command->generateDescriptions,
            $forcePortmaster
        );
    }
}
