<?php

namespace App\Command;

readonly class PostProcessCommand implements TargetableCommandInterface
{
    public const NAME = 'post-process';

    public function __construct(
        public string $source,
        public string $package,
        public string $strategy,
        public array $options,
        public bool $files,
        public bool $folders,
    ) {
    }

    public function getTarget(): string
    {
        return sprintf(
            '%s: `%s`',
            $this->strategy,
            $this->source
        );
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
