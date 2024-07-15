<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\Provider\PackagedImagePathProvider;
use App\Util\Path;
use Intervention\Image\ImageManager;

class InnerMaskPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;

    public const NAME = 'inner_mask';

    public function __construct(
        readonly private Path $path,
        readonly private PackagedImagePathProvider $packagedImagePathProvider
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function process(PostProcessCommand $command): void
    {
        $this->setupSaveBehaviour(false);

        $options = [];
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);
        $this->processWorkset($images, $options);
        $this->mirrorTemporaryFolderIfRequired($images);
    }

    private function processWorkset(array $files, array $options): void
    {
        foreach ($files as $originalFilePath) {
            $manager = ImageManager::imagick();
            $canvasX = 640;
            $canvasY = 480;
            $canvas = $manager->create($canvasX, $canvasY);

            // insert the image on top
            $originalImage = $manager->read($originalFilePath);
            $canvas->place($originalImage);
            $canvas->crop(640, 396, 0, 42);

            $newCanvas = $manager->create($canvasX, $canvasY);
            $newCanvas->place($canvas, 'center');

            $newCanvas->save($this->getSavePath($originalFilePath));
        }
    }
}
