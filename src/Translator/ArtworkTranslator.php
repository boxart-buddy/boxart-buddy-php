<?php

namespace App\Translator;

use App\ApplicationConstant;
use App\Command\CommandNamespace;
use App\Model\Artwork;
use App\Skyscraper\CacheReader;
use App\Translator\Fuzzy\FuzzyMatchingTranslator;
use App\Util\Path;
use App\Util\SkyscraperResourceImageSizingHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Twig\TwigFunction;

class ArtworkTranslator
{
    private array $runtimeTokenMemoization = [];
    private array $translationAddedForArtwork = [];
    private FuzzyMatchingTranslator $translator;

    public function __construct(
        readonly private Path $path,
        readonly private CacheReader $cacheReader,
        readonly private SkyscraperResourceImageSizingHelper $skyscraperResourceImageSizingHelper,
    ) {
        $this->translator = new FuzzyMatchingTranslator('default');
        $this->translator->setFallbackLocales(['default']);
        $this->translator->addLoader('array', new ArrayLoader());
    }

    private function loadTranslations(Artwork $artwork): void
    {
        $filesystem = new Filesystem();

        // only loads for a given artwork one time
        if (isset($this->translationAddedForArtwork[$artwork->absoluteFilepath])) {
            return;
        }

        // sketchy - relies on artwork template structure
        $tokenPath = dirname($artwork->absoluteFilepath, 2);
        // setup and load translations
        $finder = new Finder();
        $templateTokenPath = Path::join($tokenPath, 'tokens');
        if ($filesystem->exists($templateTokenPath)) {
            $finder->in($templateTokenPath);
            $finder->files()->name('*.yml');
            $this->loadFoundTranslations($finder);
        }

        // load ones from common
        $finder = new Finder();
        $finder->in($this->path->joinWithBase('resources', 'common', 'tokens'));
        $finder->files()->name('*.yml');
        $this->loadFoundTranslations($finder);

        $this->translationAddedForArtwork[$artwork->absoluteFilepath] = true;
    }

    private function loadFoundTranslations(Finder $finder): void
    {
        foreach ($finder as $file) {
            $translationData = Yaml::parseFile($file->getRealPath());
            // assume yml file is keyed by platform
            foreach ($translationData as $platformName => $translations) {
                $this->translator->addResource(
                    'array',
                    $translations,
                    $platformName,
                    // use the filename as the domain name
                    $file->getFilenameWithoutExtension()
                );
            }
        }
    }

    public function addRuntimeTranslationTokens(array $tokens): void
    {
        // ensure identical tokens only added once
        $hash = hash('xxh3', serialize($tokens));
        if (isset($this->runtimeTokenMemoization[$hash])) {
            return;
        }

        $this->translator->addResource(
            'array',
            $tokens,
            'default',
            // use 'general' as the domain (?)
            'general'
        );

        $this->runtimeTokenMemoization[$hash] = true;
    }

    public function translateArtwork(Artwork $artwork, string $locale, string $romAbsolutePath, CommandNamespace $namespace): string
    {
        $romName = Path::removeExtension(basename($romAbsolutePath));

        $this->loadTranslations($artwork);

        $t = ['template' => $artwork->read()];

        $twig = new TwigEnvironment(
            new TwigArrayLoader($t)
        );
        $twig->addExtension(new EmptyTranslatingTwigExtension($this->translator));

        $twig->addFunction(new TwigFunction('assetSize', function (string $resource) use ($romAbsolutePath, $locale) {
            return $this->cacheReader->getImageSizingHelperForRom($romAbsolutePath, $locale, $resource);
        }));

        // hack needed in case generating a portmaster alternative
        if ('sh' === pathinfo($romAbsolutePath, PATHINFO_EXTENSION)) {
            $locale = ApplicationConstant::FAKE_PORTMASTER_PLATFORM;
        }

        $vars = ['locale' => $locale, 'platform' => $locale];
        if ($romName) {
            $vars['rom'] = $romName;
        }

        $vars['resourcehelper'] = $this->skyscraperResourceImageSizingHelper;
        $vars['namespace'] = $namespace->value;

        return $twig->render(
            'template',
            $vars
        );
    }
}
