<?php

namespace App\ConsoleCommand\Interactive;

readonly class PromptChoices
{
    public function __construct(
        public string $package,
        public string $variant,
        public bool $artwork,
        public bool $folder,
        public bool $portmaster,
        public bool $zip,
        public bool $transfer,
        /** @var PostProcessChoice[] */
        public array $postProcessChoices = [],
        public ?string $theme = null
    ) {
    }

    public function prettyPrint(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT) ?: '{}';
    }

    public function cloneWithAdditionalPostProcessChoices(array $postProcessChoices): PromptChoices
    {
        return new PromptChoices(
            $this->package,
            $this->variant,
            $this->artwork,
            $this->folder,
            $this->portmaster,
            $this->zip,
            $this->transfer,
            array_merge($postProcessChoices, $this->postProcessChoices),
            $this->theme
        );
    }
}
