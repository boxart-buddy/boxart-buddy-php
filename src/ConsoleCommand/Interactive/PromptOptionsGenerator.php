<?php

namespace App\ConsoleCommand\Interactive;

use App\Config\Processor\TemplateMakeFileProcessor;
use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\PostProcess\BackgroundImagePostProcess;
use App\PostProcess\CounterPostProcess;
use App\PostProcess\Option\BackgroundImagePostProcessOptions;
use App\PostProcess\TextPostProcess;
use App\PostProcess\TranslationPostProcess;
use App\PostProcess\VerticalDotScrollbarPostProcess;
use App\PostProcess\VerticalScrollbarPostProcess;
use App\Provider\ThemeProvider;
use App\Util\Path;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

readonly class PromptOptionsGenerator
{
    public function __construct(
        private Path $path,
        private LoggerInterface $logger,
        private ConfigReader $configReader,
        private TemplateMakeFileProcessor $templateMakeFileProcessor,
        private ThemeProvider $themeProvider
    ) {
    }

    public function generate(): PromptOptions
    {
        $finder = new Finder();
        $filesystem = new Filesystem();

        // iterate template folders
        $finder->in($this->path->joinWithBase(FolderNames::TEMPLATE->value));
        $finder->directories()->notPath('common')->depth(0);

        $variants = [];
        $options = [];

        foreach ($finder as $folder) {
            $packageName = $folder->getFilename();
            $makePath = Path::join($folder->getPathname(), 'make.yml');
            if (!$filesystem->exists($makePath)) {
                $this->logger->warning(sprintf('make.yml missing for template package %s', $packageName));
                continue;
            }

            $make = $this->templateMakeFileProcessor->process($packageName);

            foreach ($make as $variantName => $d) {
                $description = array_key_exists('description', $d) ? sprintf('(%s) %s', $variantName, $d['description']) : $variantName;

                $variants[$packageName][$variantName] = $description;
                $options[$packageName][$variantName] = [];
                if (isset($d['artwork']['file'])) {
                    $options[$packageName][$variantName]['artwork'] = 'Build rom artwork?';
                }
                if (isset($d['folder']['file'])) {
                    $options[$packageName][$variantName]['folder'] = 'Build folder artwork?';
                }
                if ($this->shouldOfferForcePortmasterOption()) {
                    $options[$packageName][$variantName]['portmaster'] = 'Force Build portmaster artwork?';
                }
                $options[$packageName][$variantName]['counter'] = 'Include a counter?';
                $options[$packageName][$variantName]['scrollbar'] = 'Include a scrollbar?';
                $options[$packageName][$variantName]['translation'] = 'Include translations?';
                $options[$packageName][$variantName]['inner'] = 'Remove header and footer?';
                $options[$packageName][$variantName]['zip'] = 'Zip output into archive?';
                if ($this->configReader->getConfig()->sftpIp) {
                    $options[$packageName][$variantName]['transfer'] = 'Attempt SFTP Transfer?';
                }
            }
            ksort($options[$packageName]);
            ksort($variants[$packageName]);
        }
        ksort($variants);

        // get themes
        $themes = $this->themeProvider->getThemes();
        rsort($themes);
        $themes = array_combine($themes, $themes);
        $themes['default'] = 'default';

        return new PromptOptions(
            $variants,
            $options,
            array_reverse($themes)
        );
    }

    public function shouldOfferForcePortmasterOption(): bool
    {
        return $this->configReader->getConfig()->portmasterGenerateAll || !empty($this->configReader->getConfig()->portmaster);
    }

    public function isThemeable(string $package, string $variant, array $postProcessChoices): bool
    {
        foreach ($postProcessChoices as $choice) {
            if (in_array($choice->strategy, [CounterPostProcess::NAME, VerticalScrollbarPostProcess::NAME, VerticalDotScrollbarPostProcess::NAME, TranslationPostProcess::NAME])) {
                return true;
            }
        }

        $make = $this->templateMakeFileProcessor->process($package);
        $variant = $make[$variant] ?? null;
        if (null === $variant) {
            throw new \LogicException(sprintf('Cannot get make config for %s:%s', $package, $variant));
        }

        if (!isset($variant['post_process'])) {
            return false;
        }

        foreach ($variant['post_process'] as $pp) {
            if (BackgroundImagePostProcess::NAME === $pp['strategy'] && ($pp[BackgroundImagePostProcessOptions::USE_BACKGROUND_DEFAULT] ?? false)) {
                return true;
            }
            if (TextPostProcess::NAME === $pp['strategy']) {
                return true;
            }
        }

        return false;
    }
}
