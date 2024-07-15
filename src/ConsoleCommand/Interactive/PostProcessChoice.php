<?php

namespace App\ConsoleCommand\Interactive;

readonly class PostProcessChoice
{
    public function __construct(
        public string $strategy,
        public array $options
    ) {
    }
}
