<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\PostProcess\Option\VerticalDotScrollbarPostProcessOptions;
use App\PostProcess\Option\VerticalScrollbarPostProcessOptions;
use App\Provider\OrderedListProvider;
use App\Provider\PackagedImagePathProvider;
use App\Util\Path;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Psr\Log\LoggerInterface;

class VerticalDotScrollbarPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;

    public const NAME = 'vertical_dot_scrollbar';

    public function __construct(
        readonly private Path $path,
        readonly private LoggerInterface $logger,
        readonly private OrderedListProvider $orderedListProvider,
        readonly private PackagedImagePathProvider $packagedImagePathProvider
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function process(PostProcessCommand $command): void
    {
        $this->setupSaveBehaviour(true);

        $options = $this->processOptions($command->options, VerticalDotScrollbarPostProcessOptions::class);
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);
        $workset = $this->sortArtwork($images, $this->logger, $this->orderedListProvider);
        $this->processWorkset($workset, $options);
        $this->mirrorTemporaryFolderIfRequired($images);
    }

    private function processWorkset(array $files, array $options): void
    {
        foreach ($files as $originalFilePath) {
            $originalFilename = basename($originalFilePath);
            $manager = ImageManager::imagick();
            $canvasX = 640;
            $canvasY = 480;
            $canvas = $manager->create($canvasX, $canvasY);

            // insert the image on top
            $originalImage = $manager->read($originalFilePath);
            $canvas->place($originalImage);

            // Add a 'scrollbar'
            $scrollBar = $this->getDotsScrollBar($files, $options, $originalFilePath, $manager);

            $scrollBarPosition = match ($options[VerticalScrollbarPostProcessOptions::POSITION]) {
                'left' => 'top-left',
                'right' => 'top-right',
                default => throw new \RuntimeException()
            };

            $canvas->place(
                $scrollBar,
                $scrollBarPosition,
                20,
                90,
                $options[VerticalScrollbarPostProcessOptions::OPACITY]
            );

            $canvas->save($this->getSavePath($originalFilePath));
        }
    }

    private function getDotsScrollBar(array $files, array $options, string $currentFile, ImageManager $manager): ImageInterface
    {
        // could use a more elegant solution for pagination here probably but this works for now

        $dotDiameter = 8;
        $height = 300;
        $yPadding = 10;
        $width = 30;
        $maxNumberOfDots = (int) floor($height / ($dotDiameter + 8));
        $numberOfDots = min(count($files), $maxNumberOfDots);
        $spaces = $numberOfDots - 1;

        $totalSpace = floor($height - ($numberOfDots * $dotDiameter));

        $singleSpace = floor($totalSpace / $spaces);
        $borderColor = $options[VerticalDotScrollbarPostProcessOptions::DOTCOLOR];
        $dotColor = $options[VerticalDotScrollbarPostProcessOptions::DOTCOLOR];
        $activeDotBorder = 4;

        $scrollBar = $manager->create($width, $height + (2 * $yPadding));

        $totalFiles = count($files);

        $currentPosition = (int) array_search($currentFile, $files);

        $itemsPerPage = (int) max(round($totalFiles / $numberOfDots), 1);

        $currentPage = (int) floor($currentPosition / $itemsPerPage);

        for ($x = 0; $x < $numberOfDots; ++$x) {
            $activeDot = ($x == $currentPage);
            $yPos = (int) ($yPadding + ($x * $dotDiameter) + ($x * $singleSpace));
            $scrollBar->drawCircle($width / 2, $yPos, function (CircleFactory $circle) use ($dotDiameter, $dotColor, $borderColor, $activeDot, $activeDotBorder) {
                $circle->radius($dotDiameter / 2);
                if (!$activeDot) {
                    $circle->background($dotColor);
                }
                // only if selected
                if ($activeDot) {
                    $circle->border($borderColor, $activeDotBorder);
                }
            });
        }

        return $scrollBar;
    }
}
