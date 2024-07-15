<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\PostProcess\Option\OffsetWithSiblingsPostProcessOptions;
use App\Provider\OrderedListProvider;
use App\Provider\PackagedImagePathProvider;
use App\Util\Path;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;

class OffsetWithSiblingsPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;

    public const NAME = 'offset_with_siblings';

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

        $options = $this->processOptions($command->options, OffsetWithSiblingsPostProcessOptions::class);
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);
        $workset = $this->sortArtwork($images, $this->logger, $this->orderedListProvider);
        $this->processWorkset($workset, $options);
        $this->mirrorTemporaryFolderIfRequired($images);
    }

    private function processWorkset(array $files, array $options): void
    {
        $count = 0;
        $workset = [];

        $siblingsAmount = (int) $options[OffsetWithSiblingsPostProcessOptions::SIBLING_COUNT];
        $totalSiblingCount = (int) ($siblingsAmount * 2) + 1;

        foreach ($files as $currentFile) {
            $set = [];

            for ($x = 0; $x < $totalSiblingCount; ++$x) {
                $siblingKey = (int) ($count - (($x - $siblingsAmount) * -1));

                $set[$x] = $files[$siblingKey] ?? null;
            }

            $workset[] = $set;
            ++$count;
        }

        // add first/last siblings to allow looping type display
        if (isset($options[OffsetWithSiblingsPostProcessOptions::LOOP]) && true === $options[OffsetWithSiblingsPostProcessOptions::LOOP]) {
            for ($x = 0; $x < $totalSiblingCount; ++$x) {
                $worksetKey = $siblingsAmount - $x;

                if ($worksetKey < 0) {
                    $amendKey = $count + $worksetKey;
                    if (!isset($workset[$amendKey])) {
                        continue;
                    }
                    for ($y = 0; $y < $siblingsAmount; ++$y) {
                        $increment = $y + 1;
                        $newPosition = ($amendKey + $increment) % $count;
                        $workset[$amendKey][$siblingsAmount + ($y + 1)] = $files[$newPosition] ?? null;
                    }
                }
                if ($worksetKey > 0) {
                    $amendKey = (-1) + $worksetKey;
                    if (!isset($workset[$amendKey])) {
                        continue;
                    }
                    for ($y = 0; $y < $siblingsAmount; ++$y) {
                        $increment = ($y + 1) * -1;
                        $newPosition = ($amendKey + $increment) % $count;
                        if ($newPosition < 0) {
                            $newPosition += $count;
                        }
                        $workset[$amendKey][$siblingsAmount - ($y + 1)] = $files[$newPosition] ?? null;
                    }
                }
            }
        }

        $iteration = 0;

        foreach ($workset as $set) {
            ++$iteration;
            $canvasX = 640;
            $canvasY = 480;
            $manager = ImageManager::imagick();
            $canvas = $manager->create($canvasX, $canvasY);

            $middleKey = floor(count($set) / 2);

            $siblings = $this->reorderArrayOutToIn($set);
            $originalImage = array_pop($siblings);

            $circle = $options[OffsetWithSiblingsPostProcessOptions::CIRCLE] ?? null;

            foreach ($siblings as $siblingKey => $sibling) {
                if (null === $sibling) {
                    continue;
                }

                $offsetIndex = (int) ($siblingKey - $middleKey);

                // skip this sibling if render behind or ahead is chosen
                if ($offsetIndex < 0 && 'ahead' === $options[OffsetWithSiblingsPostProcessOptions::RENDER]) {
                    continue;
                }
                if ($offsetIndex > 0 && 'behind' === $options[OffsetWithSiblingsPostProcessOptions::RENDER]) {
                    continue;
                }

                $siblingImage = $manager->read($sibling);

                $scale = $options[OffsetWithSiblingsPostProcessOptions::SCALE] ?? null;

                // 'regular' mode always calcuated
                $totalOffsetX = (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X] * $offsetIndex;
                $totalOffsetY = (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y] * $offsetIndex;

                if ('fixed' === $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X_MODE]) {
                    $totalOffsetX = (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X];
                }
                if ('fixed' === $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y_MODE]) {
                    $totalOffsetY = (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y];
                }

                if ($scale) {
                    $targetWidth = $this->getScaledValue($canvasX, $scale, $siblingsAmount, $offsetIndex);

                    $siblingImage->scaleDown(width: $targetWidth);
                    if ('scale' === $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X_MODE]) {
                        $totalOffsetX = $this->getTargetOffset(
                            $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X], $scale, $offsetIndex, $totalSiblingCount
                        );
                    }
                    if ('scale' === $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y_MODE]) {
                        $totalOffsetY = $this->getTargetOffset(
                            $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y], $scale, $offsetIndex, $totalSiblingCount
                        );
                    }

                    $currentScale = $targetWidth / 640;
                    if ('adjust' === $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X_MODE]) {
                        $oX = $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X];
                        $totalOffsetX = ($oX <=> 0) * (abs($oX) - abs($oX) * $currentScale);
                    }
                    if ('adjust' === $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y_MODE]) {
                        $oY = $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y];
                        $totalOffsetY = ($oY <=> 0) * (abs($oY) - abs($oY) * $currentScale);
                    }
                }

                if ($circle) {
                    $xy = $this->calculateRelativeCircleCoordinates(
                        $circle,
                        $options[OffsetWithSiblingsPostProcessOptions::CIRCLE_RADIUS],
                        $offsetIndex,
                        $siblingsAmount
                    );
                    $r = round((90 / $siblingsAmount) * $offsetIndex);

                    // adding the offset option will offset the entire 'wheel'
                    $totalOffsetX = $xy['x'] + (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X];
                    $totalOffsetY = $xy['y'] + (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y];

                    $siblingImage->core()->native()->rotateImage(new \ImagickPixel('#00000000'), $r);
                }

                $effect = $options[OffsetWithSiblingsPostProcessOptions::EFFECT] ?? null;

                if ($effect) {
                    $siblingImage = match ($effect) {
                        'greyscale' => $siblingImage->greyscale(),
                        'blur' => $siblingImage->blur(3),
                        'pixelate' => $siblingImage->pixelate(4),
                        default => throw new \InvalidArgumentException(sprintf('Unknown effect `%s`', $effect)),
                    };
                }

                $opacity = $options[OffsetWithSiblingsPostProcessOptions::OPACITY] ?? null;
                if ($opacity) {
                    $targetOpacity = $this->getScaledValue(100, $opacity, $siblingsAmount, $offsetIndex);
                    $canvas->core()->native()->evaluateImage(
                        \Imagick::EVALUATE_MULTIPLY,
                        $targetOpacity / 100,
                        \Imagick::CHANNEL_ALPHA
                    );
                }

                $canvas->place(
                    $siblingImage,
                    'center',
                    $totalOffsetX,
                    $totalOffsetY,
                );
            }

            // insert the original 'middle' image on top
            if (!$circle) {
                $canvas->place($originalImage);
            }

            // if circle then the central image can also be offset
            if ($circle) {
                $canvas->place(
                    $originalImage,
                    'top-left',
                    (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_X],
                    (int) $options[OffsetWithSiblingsPostProcessOptions::OFFSET_Y]
                );
            }

            $canvas->save($this->getSavePath($originalImage));
        }
    }

    public function calculateRelativeCircleCoordinates(string $layout, int $radius, int $index, int $siblingAmount): array
    {
        // only works with half circles
        $t = 90 / $siblingAmount;

        $circleCentreX = 0;
        $circleCentreY = 0;
        $t = $t * $index;

        switch ($layout) {
            case 'half-circle-right':
                $t = $t + 180;
                break;
            case 'half-circle-top':
                $t = $t + 90;
                break;
            case 'half-circle-bottom':
                $t = $t + 270;
                break;
        }

        $x = $radius * cos(deg2rad($t)) + $circleCentreX;
        $y = $radius * sin(deg2rad($t)) + $circleCentreY;

        switch ($layout) {
            case 'half-circle-right':
                $x = $x + $radius;
                break;
            case 'half-circle-top':
                $y = $y - $radius;
                break;
            case 'half-circle-bottom':
                $y = $y + $radius;
                break;
            case 'half-circle-left':
                $x = $x - $radius;
                break;
        }

        return [
            'x' => round($x),
            'y' => round($y),
        ];
    }

    private function getScaledValue(int $start, float $multipler, int $numSteps, int $currentStep): int
    {
        // Calculate the logarithmic step factor
        $logFactor = log(($multipler * $start) / $start) / $numSteps;

        // Generate each step based on the logarithmic scale
        for ($i = 0; $i <= $numSteps; ++$i) {
            if (abs($currentStep) === $i) {
                return (int) round($start * exp($logFactor * $i));
            }
        }

        throw new \LogicException();
    }

    private function getTargetOffset(int $offset, float $scale, int $offsetIndex, float $logScale): int
    {
        $diff = (int) floor($offset - ($offset * $scale));

        $mult = log(abs($offsetIndex), $logScale);

        return (int) (($offset - min(round($diff * $mult), $diff)) * $offsetIndex);
    }

    private function reorderArrayOutToIn(array $source): array
    {
        $keys = array_keys($source);
        $result = [];
        $n = count($keys);

        // Left and right pointers
        $left = 0;
        $right = $n - 1;

        // Loop to rearrange the keys
        while ($left <= $right) {
            if ($left <= $right) {
                $result[$keys[$left]] = $source[$keys[$left]];
                ++$left;
            }
            if ($left <= $right) {
                $result[$keys[$right]] = $source[$keys[$right]];
                --$right;
            }
        }

        return $result;
    }
}
