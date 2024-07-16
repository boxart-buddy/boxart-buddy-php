<?php

namespace App\PostProcess;

use App\Command\Factory\CommandFactory;
use App\Command\GenerateFolderArtworkCommand;
use App\Command\GenerateRomArtworkCommand;
use App\Command\Handler\GenerateFolderArtworkHandler;
use App\Command\Handler\GenerateRomArtworkHandler;
use App\Command\PostProcessCommand;
use App\FolderNames;
use App\PostProcess\Option\OverlayArtworkGenerationPostProcessOptions;
use App\Provider\PackagedImagePathProvider;
use App\Util\Finder;
use App\Util\Path;
use Intervention\Image\ImageManager;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Allows you to run another skyscraper artwork generation,
 * handy to overlay wheels and logos etc. after other post-processing.
 */
#[WithMonologChannel('postprocessing')]
class OverlayArtworkGenerationPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;

    public const NAME = 'artwork_generation';

    public function __construct(
        readonly private Path $path,
        readonly private CommandFactory $commandFactory,
        readonly private GenerateRomArtworkHandler $generateRomArtworkHandler,
        readonly private GenerateFolderArtworkHandler $generateFolderArtworkHandler,
        readonly private LoggerInterface $logger,
        readonly private PackagedImagePathProvider $packagedImagePathProvider,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function process(PostProcessCommand $command): void
    {
        $this->setupSaveBehaviour(false);

        $options = $this->processOptions($command->options, OverlayArtworkGenerationPostProcessOptions::class);
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);

        $this->logger->info(
            sprintf(
                'Running post processor `artwork-generation` with command %s and options `%s`',
                json_encode($command),
                json_encode($options)
            )
        );

        $commands = $this->commandFactory->getGenerateArtworkCommandsForFolder(
            $command->source,
            $options['artwork_package'],
            $options['artwork_file'],
            $options['folder_package'],
            $options['folder_file'],
            $options['token'],
            false,
            false
        );

        // hack to wipe the output folder every time to ensure no clashes with earlier generations
        $filesystem = new Filesystem();
        $outputFolder = $this->path->joinWithBase(FolderNames::TEMP->value, 'output');
        $tempArtworkPath = $this->path->joinWithBase(FolderNames::TEMP->value, 'artwork_tmp');

        if ($filesystem->exists($outputFolder)) {
            $filesystem->remove($outputFolder);
        }

        if ($filesystem->exists($tempArtworkPath)) {
            $filesystem->remove($tempArtworkPath);
        }

        foreach ($commands as $cmd) {
            if ($cmd instanceof GenerateRomArtworkCommand) {
                $this->generateRomArtworkHandler->handle($cmd);
            }
            if ($cmd instanceof GenerateFolderArtworkCommand) {
                $this->generateFolderArtworkHandler->handle($cmd);
            }
        }

        $this->processWorkset($images, $options);
        $this->mirrorTemporaryFolderIfRequired($images);
    }

    private function processWorkset(array $files, array $options): void
    {
        $generatedFolder = $this->path->joinWithBase(
            FolderNames::TEMP->value,
            'output',
            'generated_artwork'
        );

        foreach ($files as $originalFilePath) {
            $originalFilename = basename($originalFilePath);

            $manager = ImageManager::imagick();
            $canvasX = 640;
            $canvasY = 480;
            $canvas = $manager->create($canvasX, $canvasY);

            $finder = new Finder();
            $finder->in($generatedFolder);
            $pattern = '#/covers/#';

            $finder->files()->path($pattern);
            $finder->name($originalFilename);

            if (1 !== count($finder)) {
                $this->logger->warning(
                    sprintf('%s matching images found when postprocessing with artwork, possible incorrect image inserted: %s', count($finder), $originalFilename)
                );
            }

            if (!$finder->hasResults()) {
                $this->logger->warning(
                    sprintf('No image found matching original filename %s, probable issue when generating artwork in post process', $originalFilename)
                );

                return;
            }

            $file = $finder->first();
            $generatedImagePath = $file->getRealPath();

            $layer = $options[OverlayArtworkGenerationPostProcessOptions::LAYER];

            if ('bottom' === $layer) {
                $generatedImage = $manager->read($generatedImagePath);
                $canvas->place($generatedImage,
                    'center',
                    $options[OverlayArtworkGenerationPostProcessOptions::OFFSET_GENERATED_X],
                    $options[OverlayArtworkGenerationPostProcessOptions::OFFSET_GENERATED_Y]
                );
            }

            $canvas->place(
                $originalFilePath,
                'center',
                $options[OverlayArtworkGenerationPostProcessOptions::OFFSET_ORIGINAL_X],
                $options[OverlayArtworkGenerationPostProcessOptions::OFFSET_ORIGINAL_Y]
            );

            if ('top' === $layer) {
                $generatedImage = $manager->read($generatedImagePath);
                $canvas->place($generatedImage,
                    'center',
                    $options[OverlayArtworkGenerationPostProcessOptions::OFFSET_GENERATED_X],
                    $options[OverlayArtworkGenerationPostProcessOptions::OFFSET_GENERATED_Y]
                );
            }

            // save to original location
            $canvas->save($this->getSavePath($originalFilePath));
        }
    }
}
