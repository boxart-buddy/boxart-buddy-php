<?php

namespace App\Hugo;

class HugoTemplateDataFixtureEntry implements \JsonSerializable
{
    public function __construct(
        public string $name,
        public string $description
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
