<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\FolderNames;
use App\PostProcess\Option\BackgroundImagePostProcessOptions;
use App\Provider\PackagedImagePathProvider;
use App\Util\Path;
use Intervention\Image\ImageManager;
use Symfony\Component\Filesystem\Filesystem;

class BackgroundImagePostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;

    public const NAME = 'background';

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

        $options = $this->processOptions($command->options, BackgroundImagePostProcessOptions::class);
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);
        $this->processWorkset($images, $options);
        $this->mirrorTemporaryFolderIfRequired($images);
    }

    private function processWorkset(array $files, array $options): void
    {
        $filesystem = new Filesystem();

        foreach ($files as $originalFilePath) {
            $manager = ImageManager::imagick();
            $canvasX = 640;
            $canvasY = 480;
            $canvas = $manager->create($canvasX, $canvasY);

            if (!isset($options[BackgroundImagePostProcessOptions::BACKGROUND]) && !isset($options[BackgroundImagePostProcessOptions::BACKGROUND_DEFAULT]) && !isset($options[BackgroundImagePostProcessOptions::OVERLAY])) {
                throw new \RuntimeException('Background, Background Default and/or Overlay options are required');
            }

            if (isset($options[BackgroundImagePostProcessOptions::BACKGROUND_DEFAULT]) && $options[BackgroundImagePostProcessOptions::USE_BACKGROUND_DEFAULT]) {
                $bg = $this->path->joinWithBase(
                    FolderNames::TEMP->value,
                    'post-process',
                    'resources',
                    $options[BackgroundImagePostProcessOptions::BACKGROUND_DEFAULT]
                );

                if (!$filesystem->exists($bg)) {
                    throw new \InvalidArgumentException(sprintf('Background image "%s" does not exist', $bg));
                }

                $canvas->place($bg);
            }

            if (isset($options[BackgroundImagePostProcessOptions::BACKGROUND])) {
                $bg = $this->path->joinWithBase(
                    FolderNames::TEMP->value,
                    'post-process',
                    'resources',
                    $options[BackgroundImagePostProcessOptions::BACKGROUND]
                );

                if (!$filesystem->exists($bg)) {
                    throw new \InvalidArgumentException(sprintf('Background image "%s" does not exist', $bg));
                }

                $canvas->place($bg);
            }

            // insert the image on top
            $originalImage = $manager->read($originalFilePath);
            $canvas->place($originalImage);

            if (isset($options[BackgroundImagePostProcessOptions::OVERLAY])) {
                // overlay
                $overlay = $this->path->joinWithBase(
                    FolderNames::TEMP->value,
                    'post-process',
                    'resources',
                    $options[BackgroundImagePostProcessOptions::OVERLAY]
                );

                if (!$filesystem->exists($overlay)) {
                    throw new \InvalidArgumentException(sprintf('Overlay image "%s" does not exist', $overlay));
                }

                $canvas->place($overlay);
            }

            $canvas->save($this->getSavePath($originalFilePath));
        }
    }
}
