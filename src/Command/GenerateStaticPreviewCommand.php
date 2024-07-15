<?php

namespace App\Command;

readonly class GenerateStaticPreviewCommand implements TargetableCommandInterface
{
    public const NAME = 'generate-static-preview';

    public function __construct(
        public string $target,
        public string $previewName
    ) {
    }

    public function getTarget(): string
    {
        return 'static';
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
