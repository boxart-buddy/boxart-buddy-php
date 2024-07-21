<?php

namespace App\Skyscraper;

use App\Config\Reader\ConfigReader;
use App\Event\CommandProcessingStageProgressedEvent;
use App\Event\CommandProcessingStageStartedEvent;
use App\Skyscraper\Event\CacheCrushStartedEvent;
use App\Skyscraper\Event\ImageCrushedEvent;
use App\Util\ImageSizing;
use App\Util\Path;
use Intervention\Image\ImageManager;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path as SymfonyPath;
use Symfony\Component\Finder\Finder;

readonly class CacheCrusher
{
    public function __construct(
        private ConfigReader $configReader,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function crush(): void
    {
        $finder = new Finder();
        $finder->in(SymfonyPath::canonicalize($this->configReader->getConfig()->skyscraperCacheFolderPath));
        $finder->path('#^.*/(screenscraper)/.*$#')->files();

        $optimizerChain = OptimizerChainFactory::create();

        $this->eventDispatcher->dispatch(new CacheCrushStartedEvent(count($finder)));
        $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent('crushing-images', true, count($finder)));

        foreach ($finder as $file) {
            $imageData = getimagesize($file->getRealPath()) ?: null;
            if (!$imageData) {
                continue;
            }
            $width = $imageData[0];
            $height = $imageData[1];

            $resized = $this->resizeImage($file->getRealPath(), $width, $height);

            $optimizerChain->optimize($file->getRealPath());

            $this->eventDispatcher->dispatch(new ImageCrushedEvent($resized));

            $a = basename(dirname($file->getRealPath(), 3));
            $b = basename(dirname($file->getRealPath(), 2));
            $c = $file->getFilename();
            $message = Path::join($a, $b, $c);
            $this->eventDispatcher->dispatch(new CommandProcessingStageProgressedEvent('crushing-images', $message));
        }
    }

    public function quickidXmlToEmpty(): void
    {
        $filesystem = new Filesystem();
        $finder = new Finder();
        $finder->in(SymfonyPath::canonicalize($this->configReader->getConfig()->skyscraperCacheFolderPath));
        $finder->files()->name('quickid.xml');
        foreach ($finder as $file) {
            $filesystem->dumpFile($file->getRealPath(), '<?xml version="1.0" encoding="UTF-8"?><quickids></quickids>');
        }
    }

    private function resizeImage(string $filePath, int $width, int $height): bool
    {
        $manager = ImageManager::imagick();
        $image = $manager->read($filePath);

        $sizing = new ImageSizing($image->width(), $image->height());
        $cover = $sizing->cover($width, $height);

        if ($width > $cover['w'] || $height > $cover['h']) {
            $image->scaleDown($cover['w'], $cover['h']);
            $image->save();

            return true;
        }

        return false;
    }
}
