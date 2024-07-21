<?php

namespace App\Skyscraper\Event;

use App\Skyscraper\CacheCrushReport;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class CrushEventListener
{
    public function __construct(private CacheCrushReport $cacheCrushReport)
    {
    }

    #[AsEventListener]
    public function onCacheCrushStartedEvent(CacheCrushStartedEvent $event): void
    {
        $this->cacheCrushReport->setCount($event->count);
    }

    #[AsEventListener]
    public function onImageCrushedEvent(ImageCrushedEvent $event): void
    {
        if ($event->resized) {
            $this->cacheCrushReport->addToResizeCount();
        }
    }
}
