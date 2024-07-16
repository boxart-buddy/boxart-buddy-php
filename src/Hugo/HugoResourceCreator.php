<?php

namespace App\Hugo;

use App\Config\Processor\TemplateMakeFileProcessor;
use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Util\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

readonly class HugoResourceCreator
{
    public function __construct(
        private Path $path,
        private TemplateMakeFileProcessor $templateMakeFileProcessor,
        private ConfigReader $configReader
    ) {
    }

    public function copyTemplatePreviewsToStatic(string $path): void
    {
        $filesystem = new Filesystem();
        $finder = new Finder();
        $finder->in($this->path->joinWithBase(FolderNames::TEMPLATE->value, '*', 'preview'));
        $finder->files()->name('*.png')->name('*.webp');

        $out = Path::join($path, 'static', 'template', 'preview');

        $filesystem->remove($out);
        $filesystem->mkdir($out);

        foreach ($finder as $file) {
            $filesystem->copy(
                $file->getRealPath(),
                Path::join($out, $file->getFilename()),
            );
        }
    }

    public function createHugoDataFixtureForThemes(string $path): void
    {
        $filesystem = new Filesystem();

        $themes = $this->configReader->getConfig()->previewThemes;
        $out = Path::join($path, 'docs-data', 'themes.json');
        $filesystem->dumpFile($out, json_encode($themes, JSON_PRETTY_PRINT) ?: '{}');
    }

    public function createHugoDataFixtureForTemplates(string $path): void
    {
        $filesystem = new Filesystem();
        $finder = new Finder();
        $finder->in($this->path->joinWithBase(FolderNames::TEMPLATE->value, '*'));
        $finder->files()->name('make.yml');

        $out = Path::join($path, 'docs-data', 'templates.json');

        $entries = [];

        foreach ($finder as $file) {
            $templateName = basename($file->getPath());
            $make = $this->templateMakeFileProcessor->process($templateName);
            foreach ($make as $variantName => $variantData) {
                $entries[] = $this->createHugoTemplateDataFixtureEntry($templateName, $variantName, $variantData);
            }
        }

        $filesystem->dumpFile($out, json_encode($entries, JSON_PRETTY_PRINT) ?: '{}');
    }

    private function createHugoTemplateDataFixtureEntry(
        string $templateName,
        string $variantName,
        array $variant
    ): HugoTemplateDataFixtureEntry {
        $previewName = $variant['package_name'].'.webp';
        $previewPath = sprintf('/docs/template/preview/%s', $previewName);
        $themePreviewPaths = [];
        $themePreviewPaths['default'] = $previewPath;

        $filesystem = new Filesystem();
        foreach ($this->configReader->getConfig()->previewThemes as $theme) {
            $themePreviewName = sprintf('%s-%s.webp', $variant['package_name'], $theme);
            $themePreviewPath = $this->path->joinWithBase(FolderNames::TEMPLATE->value, $templateName, 'preview', $themePreviewName);
            if ($filesystem->exists($themePreviewPath)) {
                $themePreviewPaths[$theme] = sprintf('/docs/template/preview/%s', $themePreviewName);
            }
        }

        return new HugoTemplateDataFixtureEntry(
            $templateName,
            $variantName,
            $variant['notes'],
            $previewPath,
            $themePreviewPaths,
            $variant['metadata']['type'],
            $variant['metadata']['interface'],
            true,
            isset($variant['folder']['file']) && 'null.xml' !== $variant['folder']['file'],
        );
    }
}
