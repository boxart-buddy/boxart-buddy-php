<?php

namespace App\Command;

readonly class GenerateRomArtworkCommand implements TargetableCommandInterface
{
    public const NAME = 'generate-rom-artwork';

    public function __construct(
        public string $artworkPackage,
        public string $artwork,
        public array $tokens,
        public string $romAbsolutePath,
        public bool $generateDescriptions,
        public ?string $platform,
        public bool $forcePortmaster
    ) {
    }

    public function getTarget(): string
    {
        return basename($this->romAbsolutePath);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
