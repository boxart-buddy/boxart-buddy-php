<?php

namespace App\Command\Factory;

use App\Command\BuildCommandCollection;
use App\Command\CommandInterface;
use App\Command\CommandNamespace;
use App\Command\CompressPackageCommand;
use App\Command\CopyBackPreviewCommand;
use App\Command\CopyResourcesCommand;
use App\Command\GenerateEmptyImageCommand;
use App\Command\GenerateFolderArtworkCommand;
use App\Command\GenerateRomArtworkCommand;
use App\Command\OptimizeCommand;
use App\Command\PackageCommand;
use App\Command\TransferCommand;
use App\Command\Transformer\MakeConfigurationTransformer;
use App\Config\Processor\TemplateMakeFileProcessor;
use App\Config\Reader\ConfigReader;
use App\ConsoleCommand\Interactive\PromptChoices;

readonly class BuildCommandCollectionFactory
{
    public function __construct(
        private ConfigReader $configReader,
        private CommandFactory $commandFactory,
        private TemplateMakeFileProcessor $templateMakeFileProcessor,
        private MakeConfigurationTransformer $makeConfigurationTransformer
    ) {
    }

    private function getMakeVariant(PromptChoices $choices): array
    {
        $make = $this->templateMakeFileProcessor->process($choices->package);

        $makeVariant = $make[$choices->variant];

        // patch in postProcess choices
        foreach ($choices->postProcessChoices as $postProcessChoice) {
            $makeVariant['post_process'][] = ['strategy' => $postProcessChoice->strategy, ...$postProcessChoice->options];
        }

        if ($choices->theme) {
            $makeVariant = $this->makeConfigurationTransformer->transformForTheme(
                $makeVariant,
                $choices->theme
            );
        }

        return $makeVariant;
    }

    public function create(PromptChoices $choices): BuildCommandCollection
    {
        $config = $this->configReader->getConfig();

        $makeVariant = $this->getMakeVariant($choices);
        $packageName = $makeVariant['package_name'];

        $buildCommandCollection = new BuildCommandCollection();

        $templatePackages = $this->getTemplatePackages($choices->package, $makeVariant);

        $buildCommandCollection->setCopyResourcesCommand(new CopyResourcesCommand($templatePackages));
        $buildCommandCollection->setPackageCommand(new PackageCommand($packageName));

        if ($config->shouldOptimize) {
            $buildCommandCollection->setOptimizeCommand(new OptimizeCommand($packageName, $config->convertToJpg, $config->jpgQuality));
        }
        if ($choices->zip) {
            $nukeOptions = [
                CommandNamespace::ARTWORK->value => $choices->artwork,
                CommandNamespace::FOLDER->value => $choices->folder,
                CommandNamespace::PORTMASTER->value => $choices->portmaster,
            ];

            $buildCommandCollection->setCompressPackageCommand(new CompressPackageCommand($packageName, $nukeOptions));
        }
        if ($choices->transfer) {
            $buildCommandCollection->setTransferCommand(new TransferCommand($packageName, $choices->zip));
        }
        if ($config->copyPreviewBackToTemplate) {
            $buildCommandCollection->setCopyBackPreviewCommand(new CopyBackPreviewCommand($packageName, $choices->package));
        }

        // ensure package is set, or use current package as fallback
        $makeVariant['artwork']['package'] = $makeVariant['artwork']['package'] ?? $choices->package;
        $makeVariant['folder']['package'] = $makeVariant['folder']['package'] ?? $choices->package;

        // empty images
        if ((null === $makeVariant['artwork']['file']) && (null === $makeVariant['folder']['file'])) {
            $generateEmptyImageCommands = $this->commandFactory->createEmptyImageCommands();

            $generateEmptyImageCommands = array_filter($generateEmptyImageCommands, function (GenerateEmptyImageCommand $command) use ($choices) {
                if (!$command->isDir() && $choices->artwork) {
                    return true;
                }
                if ($command->isDir() && $choices->folder) {
                    return true;
                }

                return false;
            });

            $buildCommandCollection->setGenerateEmptyImageCommands($generateEmptyImageCommands);
        }

        // artwork
        $generateArtworkCommands = [];
        if ((null !== $makeVariant['artwork']['file']) || (null !== $makeVariant['folder']['file'])) {
            $generateArtworkCommands = $this->commandFactory->createGenerateArtworkCommands(
                $makeVariant['artwork']['package'],
                $makeVariant['artwork']['file'] ?? null,
                $makeVariant['folder']['package'],
                $makeVariant['folder']['file'] ?? null,
                $makeVariant['artwork']['token'] ?? [],
            );
        }

        if ($choices->portmaster) {
            $generateArtworkCommands = array_merge(
                $generateArtworkCommands,
                $this->commandFactory->createGenerateArtworkCommandForPortmaster(
                    $makeVariant['portmaster']['package'] ?? $choices->package,
                    $makeVariant['portmaster']['file'],
                    $makeVariant['portmaster']['token'] ?? [],
                )
            );
        }

        $generateArtworkCommands = array_filter($generateArtworkCommands, function (CommandInterface $command) use ($choices) {
            if ($command instanceof GenerateRomArtworkCommand && ($choices->artwork || $choices->portmaster)) {
                return true;
            }
            if ($command instanceof GenerateFolderArtworkCommand && $choices->folder) {
                return true;
            }

            return false;
        });

        if (!empty($generateArtworkCommands)) {
            $buildCommandCollection->setGenerateArtworkCommands($generateArtworkCommands);
        }

        // post processing
        if (isset($makeVariant['post_process']) && ($choices->artwork || $choices->folder)) {
            foreach ($makeVariant['post_process'] as $postProcessOptions) {
                if (!isset($postProcessOptions['strategy'])) {
                    throw new \InvalidArgumentException('strategy key is required on post processing node');
                }

                if ('artwork_generation' === $postProcessOptions['strategy']) {
                    $postProcessOptions['artwork_package'] = $postProcessOptions['artwork_package'] ?? $makeVariant['artwork']['package'];
                    $postProcessOptions['artwork_file'] = $postProcessOptions['artwork_file'] ?? $makeVariant['artwork']['file'];
                    $postProcessOptions['folder_package'] = $postProcessOptions['folder_package'] ?? $makeVariant['folder']['package'];
                    $postProcessOptions['folder_file'] = $postProcessOptions['folder_file'] ?? $makeVariant['folder']['file'];
                }

                $strategy = $postProcessOptions['strategy'];
                unset($postProcessOptions['strategy']);

                $buildCommandCollection->addPostProcessCommands(
                    $this->commandFactory->createPostProcessCommands(
                        $packageName,
                        $strategy,
                        $postProcessOptions,
                        $choices->artwork,
                        $choices->folder,
                    )
                );
            }
        }

        if (isset($makeVariant['post_process']) && $choices->portmaster) {
            foreach ($makeVariant['post_process'] as $postProcessOptions) {
                if (!isset($postProcessOptions['strategy'])) {
                    throw new \InvalidArgumentException('strategy key is required on post processing node');
                }

                $strategy = $postProcessOptions['strategy'];
                unset($postProcessOptions['strategy']);

                $buildCommandCollection->addPostProcessCommands([
                    $this->commandFactory->createPostProcessCommandForPortmaster(
                        $packageName,
                        $strategy,
                        $postProcessOptions
                    ),
                ]);
            }
        }

        // preview
        $buildCommandCollection->setPreviewCommands(
            $this->commandFactory->createGeneratePreviewCommands($packageName, $packageName)
        );

        return $buildCommandCollection;
    }

    private function getTemplatePackages(string $thisPackage, array $makeVariant): array
    {
        $templatePackages = [$thisPackage];
        if (isset($makeVariant['artwork']['package'])) {
            $templatePackages[] = $makeVariant['artwork']['package'];
        }
        if (isset($makeVariant['folder']['package'])) {
            $templatePackages[] = $makeVariant['folder']['package'];
        }

        return array_unique($templatePackages);
    }

    public function getPackageName(PromptChoices $choices): string
    {
        return $this->getMakeVariant($choices)['package_name'];
    }
}
