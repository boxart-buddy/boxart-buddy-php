<?php

namespace App\Command;

readonly class PreviewCommand implements TargetableCommandInterface
{
    public const NAME = 'generate-preview';

    public function __construct(
        public string $target,
        public string $previewName,
        public string $previewType
    ) {
    }

    public function getTarget(): string
    {
        return 'preview';
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
