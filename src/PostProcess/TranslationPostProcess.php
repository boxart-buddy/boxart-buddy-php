<?php

namespace App\PostProcess;

use App\Command\PostProcessCommand;
use App\FolderNames;
use App\PostProcess\Option\TranslationPostProcessOptions;
use App\Provider\PackagedImagePathProvider;
use App\Provider\PathProvider;
use App\Translator\Fuzzy\FuzzyMatchingMessageCatalogue;
use App\Util\Path;
use Intervention\Image\Colors\Rgb\Color;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

#[WithMonologChannel('postprocessing')]
class TranslationPostProcess implements PostProcessInterface
{
    use ArtworkTrait;
    use SaveImageTrait;
    use ProcessOptionsTrait;

    public const NAME = 'translation';

    public function __construct(
        readonly private Path $path,
        readonly private PathProvider $pathProvider,
        readonly private LoggerInterface $logger,
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

        $options = $this->processOptions($command->options, TranslationPostProcessOptions::class);
        $images = $this->packagedImagePathProvider->getPackagedImagePathsBySourceFolder($command->source, $command->package, $command->files, $command->folders);

        $this->processWorkset($images, $options);
        $this->mirrorTemporaryFolderIfRequired($images);
    }

    private function processWorkset(array $files, array $options): void
    {
        // read text in
        $mappingFilePath = $this->path->joinWithBase(
            FolderNames::TEMP->value,
            'post-process',
            'resources',
            $options['mapping']
        );

        $mapping = Yaml::parseFile($mappingFilePath);

        // only care about platforms we care about
        $messages = [];
        foreach ($mapping as $p => $t) {
            // currently using the whole translation catalogue which might be slow
            $messages = array_merge($messages, $t);
        }

        foreach ($files as $originalFilePath) {
            $originalFilename = basename($originalFilePath);

            $textToInsert = null;
            if (!empty($messages)) {
                $textToInsert = FuzzyMatchingMessageCatalogue::getFuzzyMatch(
                    Path::removeExtension($originalFilename),
                    $messages
                );
            }

            if (!$textToInsert) {
                continue;
            }

            $manager = ImageManager::imagick();
            $canvasX = 640;
            $canvasY = 480;
            $canvas = $manager->create($canvasX, $canvasY);

            // insert the image on top
            $originalImage = $manager->read($originalFilePath);
            $canvas->place($originalImage);

            $position = match ($options[TranslationPostProcessOptions::POSITION]) {
                'center-bottom', 'center-top' => 'center',
                'bottom-left', 'top-left' => 'left',
                'bottom-right', 'top-right' => 'right',
                default => $options[TranslationPostProcessOptions::POSITION]
            };

            $x = match ($options[TranslationPostProcessOptions::POSITION]) {
                'left', 'right', 'top-left',  'bottom-left', 'top-right','bottom-right' => 20,
                default => 0
            };

            $y = match ($options[TranslationPostProcessOptions::POSITION]) {
                'center-top', 'top-left', 'top-right' => -130,
                'center-bottom', 'bottom-left', 'bottom-right' => 130,
                'bottom','top' => 50,
                default => 0
            };
            $canvasX = match ($options[TranslationPostProcessOptions::POSITION]) {
                'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right' => 240,
                default => 320
            };
            $canvasY = match ($options[TranslationPostProcessOptions::POSITION]) {
                'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right' => 120,
                default => 80
            };

            $text = $this->getTextImage($textToInsert, $options, $canvasX, $canvasY);
            $textBgOpacity = (int) $options[TranslationPostProcessOptions::TEXT_BG_OPACITY];
            $canvas->place($text, $position, $x, $y, $textBgOpacity);

            // save to temp location
            $canvas->save($this->getSavePath($originalFilePath));
        }
    }

    private function getTextImage(string $textToAdd, array $options, int $canvasX, int $canvasY): ImageInterface
    {
        $textColor = $options[TranslationPostProcessOptions::TEXT_COLOR];
        $fontFamily = $options[TranslationPostProcessOptions::TEXT_FONT_FAMILY];
        $fontVariant = $options[TranslationPostProcessOptions::TEXT_FONT_VARIANT];

        $textBgColor = $textColor;

        // if background then we invert the front color and use the $textColor for the background instead
        $c = Color::create($textColor);
        $inverseColor = \App\Util\Color::inverse(
            $c->red()->toInt(), // @phpstan-ignore method.notFound
            $c->green()->toInt(), // @phpstan-ignore method.notFound
            $c->blue()->toInt() // @phpstan-ignore method.notFound
        );
        $textColor = sprintf('rgb(%s, %s, %s)', $inverseColor['r'], $inverseColor['g'], $inverseColor['b']);

        $manager = ImageManager::imagick();
        $text = $manager->create($canvasX, $canvasY)->fill($textBgColor);

        $fontPath = $this->pathProvider->getFontPath($fontFamily, $fontVariant);

        $text->text($textToAdd, $canvasX / 2, $canvasY / 2, function (FontFactory $font) use ($fontPath, $textColor, $canvasX) {
            $font->filename($fontPath);
            $font->size(22);
            $font->color($textColor);
            $font->align('center');
            $font->valign('middle');
            $font->lineHeight(1.9);
            $font->wrap($canvasX);
        });

        $textNative = $text->core()->native();

        $textNative->trimImage(10);
        $textNative->setImagePage(0, 0, 0, 0);

        $bgWidth = $textNative->getImageWidth() + 30;
        $bgHeight = $textNative->getImageHeight() + 10;

        $bg = $manager->create($bgWidth, $bgHeight)->fill($textBgColor);

        $bg->place($textNative, 'center');

        return $bg;
    }
}
