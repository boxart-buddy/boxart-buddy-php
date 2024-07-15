<?php

namespace App\Command;

readonly class GenerateEmptyImageCommand implements TargetableCommandInterface
{
    public const NAME = 'generate-empty-image';

    public function __construct(
        public string $absolutePath,
        public ?string $platform,
    ) {
    }

    public function getTarget(): string
    {
        return basename($this->absolutePath);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function isDir(): bool
    {
        if (strlen(pathinfo($this->absolutePath, PATHINFO_EXTENSION)) > 0) {
            return false;
        }

        return true;
    }
}
