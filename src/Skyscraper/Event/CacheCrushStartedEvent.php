<?php

namespace App\Skyscraper\Event;

use Symfony\Contracts\EventDispatcher\Event;

class CacheCrushStartedEvent extends Event
{
    public function __construct(public int $count)
    {
    }
}
