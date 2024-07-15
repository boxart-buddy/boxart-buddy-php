<?php

namespace App\Command;

readonly class PrimeCacheCommand implements CommandInterface
{
    public const NAME = 'prime-cache';

    public function __construct(public string $folderAbsolutePath, public bool $onlyMissing)
    {
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
