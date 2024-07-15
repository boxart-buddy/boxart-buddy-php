<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\Portmaster\PortmasterDataImporter;
use App\PostProcess\Option\TextPostProcessOptions;
use App\Provider\NamesProvider;
use App\Provider\PackagedImagePathProvider;
use App\Provider\PathProvider;
use App\Util\Path;
use Intervention\Image\Colors\Rgb\Color;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('postprocessing')]
class TextPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;
    use FontMetricsTrait;

    public const NAME = 'text';

    public function __construct(
        readonly private Path $path,
        readonly private PathProvider $pathProvider,
        readonly private LoggerInterface $logger,
        readonly private PackagedImagePathProvider $packagedImagePathProvider,
        readonly private NamesProvider $namesProvider,
        readonly private PortmasterDataImporter $portmasterDataImporter,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function process(PostProcessCommand $command): void
    {
        $this->setupSaveBehaviour(false);

        $options = $this->processOptions($command->options, TextPostProcessOptions::class);
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);

        $this->processWorkset($images, $options);
        $this->mirrorTemporaryFolderIfRequired($images);
    }

    private function processWorkset(array $files, array $options): void
    {
        foreach ($files as $originalFilePath) {
            $textToInsert = Path::removeExtension(basename($originalFilePath));
            $textToInsert = $this->namesProvider->getEntry($textToInsert) ?? $textToInsert;

            // if port then read the port name
            if (in_array(basename(dirname($originalFilePath, 2)), ['External - Ports'])) {
                $metadata = $this->portmasterDataImporter->getMetadataForPort($textToInsert);
                if ($metadata) {
                    $textToInsert = $metadata['title'];
                }
            }

            $manager = ImageManager::imagick();
            $canvasX = 640;
            $canvasY = 480;
            $canvas = $manager->create($canvasX, $canvasY);

            // insert the image on top
            $originalImage = $manager->read($originalFilePath);
            $canvas->place($originalImage);

            $position = match ($options[TextPostProcessOptions::POSITION]) {
                'center-bottom', 'center-top' => 'center',
                'bottom-left', 'top-left' => 'left',
                'bottom-right', 'top-right' => 'right',
                default => $options[TextPostProcessOptions::POSITION]
            };

            $x = match ($options[TextPostProcessOptions::POSITION]) {
                'left', 'right', 'top-left',  'bottom-left', 'top-right','bottom-right' => 20,
                default => 0
            };

            $y = match ($options[TextPostProcessOptions::POSITION]) {
                'center-top', 'top-left', 'top-right' => -130,
                'center-bottom', 'bottom-left', 'bottom-right' => 130,
                'bottom','top' => 50,
                default => 0
            };
            $canvasX = 560;
            $canvasY = 60;

            $text = $this->getTextImage($textToInsert, $options, $canvasX, $canvasY);
            $canvas->place($text, $position, $x, $y);

            // save to temp location
            $canvas->save($this->getSavePath($originalFilePath));
        }
    }

    private function getTextImage(string $textToAdd, array $options, int $canvasX, int $canvasY): ImageInterface
    {
        $textColor = $options[TextPostProcessOptions::TEXT_COLOR];
        $textColorAlt = $options[TextPostProcessOptions::TEXT_COLOR_ALT];
        if ($textColorAlt && $options[TextPostProcessOptions::USE_TEXT_COLOR_ALT]) {
            $textColor = $textColorAlt;
        }
        $textBgColor = $options[TextPostProcessOptions::TEXT_BG_COLOR];
        if ($options[TextPostProcessOptions::TEXT_COLOR_INVERT]) {
            list($textBgColor, $textColor) = [$textColor, $textBgColor];
        }
        $textBgOpacity = $options[TextPostProcessOptions::TEXT_BG_OPACITY];
        $bgStyle = $options[TextPostProcessOptions::TEXT_BG_STYLE];
        $fontFamily = $options[TextPostProcessOptions::TEXT_FONT_FAMILY];
        $fontVariant = $options[TextPostProcessOptions::TEXT_FONT_VARIANT];
        $textSize = $options[TextPostProcessOptions::TEXT_SIZE];
        $position = $options[TextPostProcessOptions::POSITION];
        $textShadow = $options[TextPostProcessOptions::TEXT_SHADOW];
        $trimBrackets = $options[TextPostProcessOptions::TRIM_BRACKETS];

        $manager = ImageManager::imagick();

        $fontPath = $this->pathProvider->getFontPath($fontFamily, $fontVariant);

        $textHAlign = match ($position) {
            'left', 'bottom-left', 'top-left' => 'left',
            'right', 'bottom-right', 'top-right' => 'right',
            default => 'center'
        };
        $x = match ($position) {
            'left', 'bottom-left', 'top-left' => 1,
            'right', 'bottom-right', 'top-right' => $canvasX - 1,
            default => $canvasX / 2
        };

        $fontSize = match ($textSize) {
            'xxxs' => 4 * 4,
            'xxs' => 4 * 5,
            'xs' => 4 * 6,
            's' => 4 * 7,
            'm' => 4 * 8,
            'l' => 4 * 9,
            'xl' => 4 * 10,
            'xxl' => 4 * 11,
            'xxxl' => 4 * 12,
            default => 4 * 8
        };

        if ($trimBrackets) {
            $textToAdd = $this->trimBrackets($textToAdd);
        }
        $stringCounter = 1;
        $truncated = $textToAdd; // for phpstan
        while ($stringCounter < 100) {
            $attempt = substr($textToAdd, 0, $stringCounter);
            $metrics = $this->getFontMetrics($options, $this->pathProvider, (float) $fontSize, $attempt);
            $width = $metrics['textWidth'];
            if ($width > $canvasX - 50) {
                $truncated = $truncated.'…';
                break;
            }
            $truncated = $attempt;
            ++$stringCounter;
        }
        $textToAdd = $truncated;

        $text = $manager->create($canvasX, $canvasY);
        $text->text($textToAdd, $x, $canvasY / 2, function (FontFactory $font) use ($fontPath, $textColor, $textHAlign, $canvasX, $fontSize) {
            $font->filename($fontPath);
            $font->size($fontSize);
            $font->color($textColor);
            $font->align($textHAlign);
            $font->valign('middle');
            $font->lineHeight(1.9);
            $font->wrap($canvasX);
        });

        $textNative = $text->core()->native();
        $textNative->trimImage(10);
        $textNative->setImagePage(0, 0, 0, 0);

        $bgWidth = $textNative->getImageWidth();
        $bgHeight = $fontSize;

        $bgPaddingY = 4 * 8;
        $bgPaddingX = $bgHeight + $bgPaddingY;

        if (100 !== $textBgOpacity) {
            $bgc = Color::create($textBgColor);
            $textBgColor = new Color(
                $bgc->red()->value(), // @phpstan-ignore method.notFound
                $bgc->green()->value(), // @phpstan-ignore method.notFound
                $bgc->blue()->value(), // @phpstan-ignore method.notFound
                (int) floor((255 / 100) * $textBgOpacity)
            );
        }

        $bg = $manager->create($bgWidth, $bgHeight + $bgPaddingY)->fill($textBgColor);

        $bgCanvas = $manager->create($bgWidth + $bgPaddingX + 1, $bgHeight + $bgPaddingY);

        // apply style to edge of bgcanvas
        if ('pill' === $bgStyle) {
            $radius = $bgPaddingX / 2;
            // left
            $bgCanvas->drawCircle($radius, ($bgHeight + $bgPaddingY) / 2, function (CircleFactory $circle) use ($radius, $textBgColor) {
                $circle->radius($radius);
                $circle->background($textBgColor);
            });

            // right
            $bgCanvas->drawCircle(($bgWidth + $bgPaddingX) - $radius, ($bgHeight + $bgPaddingY) / 2, function (CircleFactory $circle) use ($radius, $textBgColor) {
                $circle->radius($radius);
                $circle->background($textBgColor);
            });
        }
        if ('square' === $bgStyle) {
            // left
            $bgCanvas->drawRectangle(0, 0, function (RectangleFactory $rectangle) use ($canvasY, $bgPaddingX, $textBgColor) {
                $rectangle->size($bgPaddingX, $canvasY);
                $rectangle->background($textBgColor);
            });

            // right
            $bgCanvas->drawRectangle($bgWidth + $bgPaddingX, 0, function (RectangleFactory $rectangle) use ($canvasY, $bgPaddingX, $textBgColor) {
                $rectangle->size($bgPaddingX, $canvasY);
                $rectangle->background($textBgColor);
            });
        }

        $bgCanvas->place($bg, 'left', $bgPaddingX / 2);

        $textYOffset = 4;

        // text shadow
        if ($textShadow) {
            $c = Color::create($textColor);
            $textShadowOffset = 2;
            $textShadowAlpha = 35;
            $textShadowColor = new Color(
                $c->red()->value(), // @phpstan-ignore method.notFound
                $c->green()->value(), // @phpstan-ignore method.notFound
                $c->blue()->value(), // @phpstan-ignore method.notFound
                (int) round((255 / 100) * $textShadowAlpha)
            );
            $bgCanvas->text($textToAdd, ($bgPaddingX / 2) + $textShadowOffset, ($canvasY / 2) + $textYOffset + $textShadowOffset, function (FontFactory $font) use ($fontPath, $textShadowColor, $textHAlign, $canvasX, $fontSize) {
                $font->filename($fontPath);
                $font->size($fontSize);
                $font->color($textShadowColor);
                $font->align($textHAlign);
                $font->valign('middle');
                $font->lineHeight(1.9);
                $font->wrap($canvasX);
            });
        }

        // write text onto BG
        $bgCanvas->text($textToAdd, $bgPaddingX / 2, ($canvasY / 2) + $textYOffset, function (FontFactory $font) use ($fontPath, $textColor, $textHAlign, $canvasX, $fontSize) {
            $font->filename($fontPath);
            $font->size($fontSize);
            $font->color($textColor);
            $font->align($textHAlign);
            $font->valign('middle');
            $font->lineHeight(1.9);
            $font->wrap($canvasX);
        });

        return $bgCanvas;
    }

    private function trimBrackets(string $input): string
    {
        return trim(preg_replace('/\s*[\(\[].*?[\)\]]\s*/', ' ', $input) ?? '');
    }
}
