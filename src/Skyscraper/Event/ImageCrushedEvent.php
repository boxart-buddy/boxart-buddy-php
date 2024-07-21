<?php

namespace App\Skyscraper\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ImageCrushedEvent extends Event
{
    public function __construct(public bool $resized)
    {
    }
}
