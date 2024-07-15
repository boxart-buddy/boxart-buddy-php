<?php

namespace App\Command;

readonly class PreviewCommand implements TargetableCommandInterface
{
    public const NAME = 'generate-animated-preview';

    public function __construct(
        public string $target,
        public string $previewName,
        public string $previewType
    ) {
    }

    public function getTarget(): string
    {
        return 'animated';
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
