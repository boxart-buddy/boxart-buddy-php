<?php

namespace App\Util;

class Time
{
    private ?int $initTime;

    public function __construct()
    {
        $this->initTime = time();
    }

    public function getInitTime(): ?int
    {
        return $this->initTime;
    }
}
