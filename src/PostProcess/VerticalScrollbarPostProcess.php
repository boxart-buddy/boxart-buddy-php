<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\PostProcess\Option\VerticalScrollbarPostProcessOptions;
use App\Provider\OrderedListProvider;
use App\Provider\PackagedImagePathProvider;
use App\Util\Path;
use Intervention\Image\Colors\Hsl\Color as HslColor;
use Intervention\Image\Colors\Hsl\Colorspace as HslColorspace;
use Intervention\Image\Colors\Rgb\Color as RgbColor;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Psr\Log\LoggerInterface;

class VerticalScrollbarPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;

    public const NAME = 'vertical_scrollbar';

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

        $options = $this->processOptions($command->options, VerticalScrollbarPostProcessOptions::class);
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);
        $workset = $this->sortArtwork($images, $this->logger, $this->orderedListProvider);
        $this->processWorkset($workset, $options);
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

            // Add a 'scrollbar'
            $scrollBar = $this->getBarScrollBar($files, $options, $originalFilePath, $manager);

            $scrollBarPosition = $options[VerticalScrollbarPostProcessOptions::POSITION];

            $canvas->place(
                $scrollBar,
                $scrollBarPosition,
                20,
                0,
                $options[VerticalScrollbarPostProcessOptions::OPACITY]
            );

            $canvas->save($this->getSavePath($originalFilePath));
        }
    }

    private function getBarScrollBar(array $files, array $options, string $currentFile, ImageManager $manager): ImageInterface
    {
        $totalFiles = count($files);
        $currentPosition = (int) array_search($currentFile, $files);

        $thumbColor = $options[VerticalScrollbarPostProcessOptions::THUMB_COLOR];
        $trackColor = $options[VerticalScrollbarPostProcessOptions::TRACK_COLOR] ?? null;

        if (!$trackColor) {
            // this doesn't work very well so probably best to define bg color....
            $c = RgbColor::create($thumbColor);
            $c = $c->convertTo(HslColorspace::class);
            if (!$c instanceof HslColor) {
                throw new \RuntimeException();
            }

            $lighterColor = new HslColor(
                $c->hue()->value(),
                max([$c->saturation()->value() - 5, 100]),
                min([$c->luminance()->value() + 15, 100])
            );
            $trackColor = $lighterColor->toString();
        }
        $height = $options[VerticalScrollbarPostProcessOptions::TRACK_HEIGHT];
        $width = $options[VerticalScrollbarPostProcessOptions::TRACK_WIDTH];
        $thumbHeight = $options[VerticalScrollbarPostProcessOptions::THUMB_HEIGHT];
        $roundedTrack = ('rounded' === $options[VerticalScrollbarPostProcessOptions::TRACK_STYLE]);

        $trackPaddingX = 2;
        $trackPaddingY = 3;
        $outerPadding = 1;

        $thumbWidth = ($width - (2 * $trackPaddingX));
        $trackRange = ($height - $thumbHeight - ($trackPaddingY * 2) - ($outerPadding * 2));

        $scrollBar = $manager->create($width + ($outerPadding * 2), $height + ($outerPadding * 2));

        $thumbYPosition = (int) round($currentPosition * ($trackRange / ($totalFiles - 1))) + $trackPaddingY + $outerPadding;
        $thumbXPosition = $outerPadding + (($width - $thumbWidth) / 2);

        // draw track
        $barRectangleHeight = $height;
        $barY = $outerPadding;
        $barX = $outerPadding;
        $circleRadius = 0;

        if ($roundedTrack) {
            $circleRadius = $width / 2;
            $barRectangleHeight = $height - ($circleRadius * 2);
            $barY = $barY + $circleRadius;
        }

        $scrollBar->drawRectangle($barX, $barY, function (RectangleFactory $rectangle) use ($barRectangleHeight, $width, $trackColor) {
            $rectangle->size($width, $barRectangleHeight);
            $rectangle->background($trackColor);
        });

        if ($roundedTrack) {
            // top
            $topCircleX = $barX + $circleRadius;
            $topCircleY = $barY;
            $scrollBar->drawCircle($topCircleX, $topCircleY, function (CircleFactory $circle) use ($circleRadius, $trackColor) {
                $circle->radius($circleRadius);
                $circle->background($trackColor);
            });

            // bottom
            $bottomCircleX = $barX + $circleRadius;
            $bottomCircleY = $height - $circleRadius - $outerPadding;
            $scrollBar->drawCircle($bottomCircleX, $bottomCircleY, function (CircleFactory $circle) use ($circleRadius, $trackColor) {
                $circle->radius($circleRadius);
                $circle->background($trackColor);
            });
        }

        // draw thumb
        $thumbCircleRadius = 0;
        if ($roundedTrack) {
            $thumbCircleRadius = $thumbWidth / 2;
            $thumbHeight = $thumbHeight - ($thumbCircleRadius * 2);
            $thumbYPosition = $thumbYPosition + $thumbCircleRadius;
        }

        $scrollBar->drawRectangle($thumbXPosition, $thumbYPosition, function (RectangleFactory $rectangle) use ($thumbWidth, $thumbHeight, $thumbColor) {
            $rectangle->size($thumbWidth, $thumbHeight);
            $rectangle->background($thumbColor);
        });

        if ($roundedTrack) {
            // top
            $thumbTopCircleX = $thumbXPosition + $thumbCircleRadius;
            $thumbTopCircleY = $thumbYPosition;
            $scrollBar->drawCircle($thumbTopCircleX, $thumbTopCircleY, function (CircleFactory $circle) use ($thumbCircleRadius, $thumbColor) {
                $circle->radius($thumbCircleRadius);
                $circle->background($thumbColor);
            });

            // bottom
            $thumbBottomCircleX = $thumbTopCircleX;
            $thumbBottomCircleY = $thumbYPosition + $thumbHeight;
            $scrollBar->drawCircle($thumbBottomCircleX, $thumbBottomCircleY, function (CircleFactory $circle) use ($thumbCircleRadius, $thumbColor) {
                $circle->radius($thumbCircleRadius);
                $circle->background($thumbColor);
            });
        }

        return $scrollBar;
    }
}
