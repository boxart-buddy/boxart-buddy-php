<?php

namespace App\Hugo;

readonly class HugoVariantDataFixtureEntry implements \JsonSerializable
{
    public function __construct(
        private string $templateName,
        private string $variantName,
        private string $notes,
        private ?string $downloadLink,
        private string $previewPath,
        private array $themePreviewPaths,
        private string $type,
        private string $interface,
        private bool $artwork,
        private bool $folder,
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
