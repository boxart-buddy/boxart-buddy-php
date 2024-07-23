<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\PostProcess\Option\CounterPostProcessOptions;
use App\Provider\OrderedListProvider;
use App\Provider\PackagedImagePathProvider;
use App\Provider\PathProvider;
use App\Util\Path;
use Intervention\Image\Colors\Rgb\Color;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Psr\Log\LoggerInterface;

class CounterPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;
    use FontTrait;

    public const NAME = 'counter';
    protected array $fontMetricCache = [];
    protected array $fontCache = [];

    public function __construct(
        readonly private LoggerInterface $logger,
        readonly private Path $path,
        readonly private OrderedListProvider $orderedListProvider,
        readonly private PathProvider $pathProvider,
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

        $options = $this->processOptions($command->options, CounterPostProcessOptions::class);
        $options = $this->reProcessOptions($options);
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

            // Add a 'counter'
            $counter = $this->getCounter($files, $options, $originalFilePath, $manager);

            $position = $options[CounterPostProcessOptions::POSITION];
            $position = match ($position) {
                'absolute-bottom-right' => 'bottom-right',
                'absolute-bottom-left' => 'bottom-left',
                'absolute-bottom' => 'bottom',
                'absolute-top' => 'top',
                default => $position
            };

            $offsetX = $options[CounterPostProcessOptions::OFFSET_X];
            $offsetY = $options[CounterPostProcessOptions::OFFSET_Y];

            $canvas->place(
                $counter,
                $position,
                $offsetX,
                $offsetY,
                $options[CounterPostProcessOptions::OPACITY]
            );

            $canvas->save($this->getSavePath($originalFilePath));
        }
    }

    private function getCounter(array $files, array $options, string $currentFile, ImageManager $manager): ImageInterface
    {
        switch ($options[CounterPostProcessOptions::VARIANT]) {
            case 'simple':
                return $this->getSimpleCounter($files, $options, $currentFile, $manager);
            case 'circular':
                return $this->getCircularCounter($files, $options, $currentFile, $manager);
        }

        throw new \InvalidArgumentException(sprintf('unknown variant %s', $options[CounterPostProcessOptions::VARIANT]));
    }

    private function getSimpleCounter(
        array $files,
        array $options,
        string $currentFile,
        ImageManager $manager,
        bool $versionForBackgroundTrim = false
    ): ImageInterface {
        $height = 60;
        $width = 160;

        $fontFamily = $options[CounterPostProcessOptions::TEXT_FONT_FAMILY];
        $fontVariant = $options[CounterPostProcessOptions::TEXT_FONT_VARIANT];
        $color = $options[CounterPostProcessOptions::TEXT_COLOR];
        $scale = $options[CounterPostProcessOptions::SCALE];
        $background = $options[CounterPostProcessOptions::BACKGROUND];
        $backgroundOpacity = $options[CounterPostProcessOptions::BACKGROUND_OPACITY];

        $bgColor = $color;

        if ($background) {
            // if background then we invert the front color and use the $color for the background instead
            $c = Color::create($color);

            $inverseColor = \App\Util\Color::inverse(
                $c->red()->toInt(), // @phpstan-ignore method.notFound
                $c->green()->toInt(), // @phpstan-ignore method.notFound
                $c->blue()->toInt() // @phpstan-ignore method.notFound
            );
            $color = sprintf('rgb(%s, %s, %s)', $inverseColor['r'], $inverseColor['g'], $inverseColor['b']);
        }

        $counter = $manager->create($width, $height);
        $total = count($files);

        $currentPosition = (int) array_search($currentFile, $files);

        $fontPath = $this->pathProvider->getFontPath($fontFamily, $fontVariant);

        $align = match ($options[CounterPostProcessOptions::POSITION]) {
            'top-right', 'right', 'bottom-right' => 'right',
            'top-left', 'left', 'bottom-left' => 'left',
            'top', 'bottom' => 'center',
            default => 'center'
        };
        $valign = match ($options[CounterPostProcessOptions::POSITION]) {
            'top-right', 'top-left', 'top' => 'top',
            'bottom-right', 'bottom-left', 'bottom' => 'bottom',
            'right', 'left' => 'middle',
            default => 'middle'
        };

        $x = match ($options[CounterPostProcessOptions::POSITION]) {
            'top-right', 'right', 'bottom-right' => $width - 5,
            'top-left', 'left', 'bottom-left' => 5,
            'top', 'bottom' => $width / 2,
            default => $width / 2
        };

        $y = $height / 2;

        $current = $currentPosition + 1;

        if ($versionForBackgroundTrim) {
            $current = $total;
        }

        $fontMetrics = $this->getFontMetrics($options, $this->pathProvider);
        $textWidth = $fontMetrics['textWidth'];
        $fontSize = round(6 * (30 / $textWidth));

        $counter->text(
            sprintf('%s / %s', $current, $total),
            $x,
            $y,
            $this->getFont($fontPath, $fontSize, $color, $align, $valign)
        );

        // trim the counter and place it back on the background to make sure it is centre aligned
        $counter->core()->native()->trimImage(10);
        $counter->core()->native()->setImagePage(0, 0, 0, 0);

        if ($versionForBackgroundTrim) {
            return $counter;
        }

        $counterAlign = match ($options[CounterPostProcessOptions::POSITION]) {
            'top-right', 'right', 'bottom-right' => 'right',
            'top-left', 'left', 'bottom-left' => 'left',
            'top', 'bottom' => 'center',
            default => 'center'
        };

        if (!$background) {
            $counterOnCanvas = $manager->create($width, $height);
            $counterOnCanvas->place($counter, $counterAlign);

            if (1 !== $scale) {
                $counterOnCanvas->scale(height: $height * $scale);
            }

            return $counterOnCanvas;
        }

        // for the background image, in order to make sure the trim background remains consistent
        // take it from the largest possible version of the counter
        $counterLargest = $this->getSimpleCounter($files, $options, $currentFile, $manager, true)->core()->native();
        $counterLargest->trimImage(10);
        $counterLargest->setImagePage(0, 0, 0, 0);

        $xPadding = 30;
        $bg = $manager->create(
            $counterLargest->getImageWidth() + $xPadding,
            $counterLargest->getImageHeight() + 20
        )->fill($bgColor);

        // $bg->core()->native()->negateImage(false, \Imagick::CHANNEL_RED | \Imagick::CHANNEL_GREEN | \Imagick::CHANNEL_BLUE);

        $canvas = $manager->create($width, $height);

        $counterX = match ($options[CounterPostProcessOptions::POSITION]) {
            'top-right', 'right', 'bottom-right', 'top-left', 'left', 'bottom-left' => $xPadding / 2,
            'top', 'bottom' => 0,
            default => 0
        };

        $canvas->place($bg, $counterAlign, 0, 0, $backgroundOpacity);
        $canvas->place($counter, $counterAlign, $counterX);

        if (1 !== $scale) {
            $canvas->scale(height: $height * $scale);
        }

        return $canvas;
    }

    private function getCircularCounter(array $files, array $options, string $currentFile, ImageManager $manager): ImageInterface
    {
        $height = 180;
        $width = 180;

        $fontFamily = $options[CounterPostProcessOptions::TEXT_FONT_FAMILY];
        $fontVariant = $options[CounterPostProcessOptions::TEXT_FONT_VARIANT];
        $color = $options[CounterPostProcessOptions::TEXT_COLOR];
        $scale = $options[CounterPostProcessOptions::SCALE];
        $background = $options[CounterPostProcessOptions::BACKGROUND];
        $backgroundOpacity = $options[CounterPostProcessOptions::BACKGROUND_OPACITY];

        $bgColor = $color;

        if ($background) {
            // if background then we invert the front color and use the $color for the background instead
            $c = Color::create($color);

            $inverseColor = \App\Util\Color::inverse(
                $c->red()->toInt(), // @phpstan-ignore method.notFound
                $c->green()->toInt(), // @phpstan-ignore method.notFound
                $c->blue()->toInt() // @phpstan-ignore method.notFound
            );
            $color = sprintf('rgb(%s, %s, %s)', $inverseColor['r'], $inverseColor['g'], $inverseColor['b']);
        }

        $counter = $manager->create($width, $height);

        $fontPath = $this->pathProvider->getFontPath($fontFamily, $fontVariant);

        $fontMetrics = $this->getFontMetrics($options, $this->pathProvider);
        $textWidth = $fontMetrics['textWidth'];

        $currentY = 78;
        $totalY = 102;

        $total = count($files);

        $current = ((int) array_search($currentFile, $files) + 1);

        $fontSize = 6 * round(30 / $textWidth);

        $counter->text(
            (string) $current,
            90,
            $currentY,
            $this->getFont($fontPath, $fontSize, $color, 'right', 'center')
        );

        $counter->text(
            (string) $total,
            90,
            $totalY,
            $this->getFont($fontPath, $fontSize, $color, 'left', 'center')
        );

        $circleRadius = match (strlen((string) $total)) {
            1 => 35,
            2 => 46,
            3 => 57,
            4 => 69,
            5 => 82,
            default => 75
        };

        // scale value should be relative to text size
        $counter->scale(height: $height * $scale);
        $circleRadius = $circleRadius * $scale;

        // $counter->crop($circleRadius * 2, $circleRadius * 2, $circleRadius, $circleRadius, 'transparent', 'center');

        if (!$background) {
            return $counter;
        }

        $bg = $manager->create(($circleRadius * 2) + 2, ($circleRadius * 2) + 2);
        $bg->drawCircle($circleRadius, $circleRadius, function (CircleFactory $circle) use ($bgColor, $circleRadius) {
            $circle->radius($circleRadius);
            $circle->background($bgColor);
        });

        $canvas = $manager->create(($circleRadius * 2) + 2, ($circleRadius * 2) + 2);

        $canvas->place($bg, 'center', 0, 0, $backgroundOpacity);
        $canvas->place($counter, 'center');

        return $canvas;
    }

    private function reProcessOptions(array $options): array
    {
        if (!isset($options[CounterPostProcessOptions::OFFSET_X])) {
            $defaultX = 40;
            $options[CounterPostProcessOptions::OFFSET_X] = match ($options[CounterPostProcessOptions::POSITION]) {
                'top-left', 'top-right', 'bottom-left', 'bottom-right', 'left', 'right' => $defaultX,
                default => 0,
            };
        }

        if (!isset($options[CounterPostProcessOptions::OFFSET_Y])) {
            $defaultY = 40;
            $options[CounterPostProcessOptions::OFFSET_Y] = match ($options[CounterPostProcessOptions::POSITION]) {
                'top-left', 'top-right','bottom-left', 'bottom-right', 'top', 'bottom' => $defaultY,
                default => 0,
            };
        }

        return $options;
    }
}
