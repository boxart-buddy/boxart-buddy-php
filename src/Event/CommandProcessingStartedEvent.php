<?php

namespace App\Event;

class CommandProcessingStartedEvent extends CommandProcessingEvent
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }
}
