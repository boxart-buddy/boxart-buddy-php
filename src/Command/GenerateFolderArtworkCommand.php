<?php

namespace App\Command;

readonly class GenerateFolderArtworkCommand implements TargetableCommandInterface
{
    public const NAME = 'generate-folder-artwork';

    public function __construct(
        public string $artworkPackage,
        public string $artwork,
        public array $tokens,
        public string $folderAbsolutePath
    ) {
    }

    public function getTarget(): string
    {
        return basename($this->folderAbsolutePath);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
