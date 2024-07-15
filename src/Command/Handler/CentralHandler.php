<?php

namespace App\Command\Handler;

use App\Command\BuildCommandCollection;
use App\Command\CommandInterface;
use App\Command\CompressPackageCommand;
use App\Command\CopyBackPreviewCommand;
use App\Command\CopyResourcesCommand;
use App\Command\GenerateEmptyImageCommand;
use App\Command\GenerateFolderArtworkCommand;
use App\Command\GenerateRomArtworkCommand;
use App\Command\OptimizeCommand;
use App\Command\PackageCommand;
use App\Command\PostProcessCommand;
use App\Command\PreviewCommand;
use App\Command\PrimeCacheCommand;
use App\Command\TransferCommand;
use App\Event\CommandProcessingStageCompletedEvent;
use App\Event\CommandProcessingStageFailedEvent;
use App\Event\CommandProcessingStageProgressedEvent;
use App\Event\CommandProcessingStageStartedEvent;
use App\FolderNames;
use App\Generator\SkippedRomImportDataGenerator;
use App\Util\Path;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

readonly class CentralHandler
{
    public function __construct(
        private CopyResourcesHandler $copyResourcesHandler,
        private GenerateRomArtworkHandler $generateRomArtworkHandler,
        private GenerateFolderArtworkHandler $generateFolderArtworkHandler,
        private PrimeCacheHandler $primeCacheHandler,
        private PreviewHandler $previewHandler,
        private PackageHandler $packageHandler,
        private CompressPackageHandler $compressPackageHandler,
        private TransferHandler $transferHandler,
        private PostProcessHandler $postProcessHandler,
        private OptimizeHandler $optimizeHandler,
        private CopyBackPreviewHandler $copyBackPreviewHandler,
        private GenerateEmptyImageHandler $generateEmptyImageHandler,
        private Path $path,
        private EventDispatcherInterface $eventDispatcher,
        private SkippedRomImportDataGenerator $skippedRomImportDataGenerator,
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        switch (get_class($command)) {
            case GenerateRomArtworkCommand::class:
                $this->generateRomArtworkHandler->handle($command);
                break;
            case GenerateFolderArtworkCommand::class:
                $this->generateFolderArtworkHandler->handle($command);
                break;
            case GenerateEmptyImageCommand::class:
                $this->generateEmptyImageHandler->handle($command);
                break;
            case CopyResourcesCommand::class:
                $this->copyResourcesHandler->handle($command);
                break;
            case PrimeCacheCommand::class:
                $this->primeCacheHandler->handle($command);
                break;
            case PreviewCommand::class:
                $this->previewHandler->handle($command);
                break;
            case PackageCommand::class:
                $this->packageHandler->handle($command);
                break;
            case CompressPackageCommand::class:
                $this->compressPackageHandler->handle($command);
                break;
            case TransferCommand::class:
                $this->transferHandler->handle($command);
                break;
            case PostProcessCommand::class:
                $this->postProcessHandler->handle($command);
                break;
            case OptimizeCommand::class:
                $this->optimizeHandler->handle($command);
                break;
            case CopyBackPreviewCommand::class:
                $this->copyBackPreviewHandler->handle($command);
                break;
            default:
                throw new \RuntimeException(sprintf('No handler registered for command of type `%s`', $command::class));
        }
    }

    // handles commands in a logical order

    /**
     * @throws \Throwable
     */
    public function handleBuildCommandCollection(BuildCommandCollection $collection): void
    {
        $this->deleteOutputFolder();
        $this->skippedRomImportDataGenerator->resetMissingRomFile();

        if ($collection->hasCopyResourcesCommand()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent(CopyResourcesCommand::NAME));
            try {
                $this->handle($collection->getCopyResourcesCommand()); // @phpstan-ignore argument.type
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent(CopyResourcesCommand::NAME));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent(CopyResourcesCommand::NAME, $e->getMessage()));
                throw $e;
            }
        }

        if ($collection->hasGenerateArtworkCommands()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent('generate-rom-and-folder-artwork', true, count($collection->getGenerateArtworkCommands())));
            try {
                foreach ($collection->getGenerateArtworkCommands() as $cmd) {
                    $this->eventDispatcher->dispatch(new CommandProcessingStageProgressedEvent('generate-rom-and-folder-artwork', $cmd->getTarget()));
                    $this->handle($cmd);
                }
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent('generate-rom-and-folder-artwork'));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent('generate-rom-and-folder-artwork', $e->getMessage()));
                throw $e;
            }
        }

        if ($collection->hasGenerateEmptyImageCommands()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent('generate-empty-images', true, count($collection->getGenerateEmptyImageCommands())));
            try {
                foreach ($collection->getGenerateEmptyImageCommands() as $cmd) {
                    $this->eventDispatcher->dispatch(new CommandProcessingStageProgressedEvent('generate-empty-images', $cmd->getTarget()));
                    $this->handle($cmd);
                }
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent('generate-empty-images'));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent('generate-empty-images', $e->getMessage()));
                throw $e;
            }
        }

        if (!$collection->hasPackageCommand()) {
            throw new \LogicException('Package command must always be present');
        }

        $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent(PackageCommand::NAME));
        try {
            $this->handle($collection->getPackageCommand()); // @phpstan-ignore argument.type
            $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent(PackageCommand::NAME));
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent(PackageCommand::NAME, $e->getMessage()));
            throw $e;
        }

        if ($collection->hasPostProcessCommands()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent(PostProcessCommand::NAME, true, count($collection->getPostProcessCommands())));
            try {
                foreach ($collection->getPostProcessCommands() as $cmd) {
                    $this->eventDispatcher->dispatch(new CommandProcessingStageProgressedEvent(PostProcessCommand::NAME, $cmd->getTarget()));
                    $this->handle($cmd);
                }
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent(PostProcessCommand::NAME));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent(PostProcessCommand::NAME, $e->getMessage()));
                throw $e;
            }
        }

        if ($collection->hasPreviewCommands()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent('preview', true, count($collection->getPreviewCommands())));
            try {
                foreach ($collection->getPreviewCommands() as $cmd) {
                    $this->eventDispatcher->dispatch(new CommandProcessingStageProgressedEvent('preview', $cmd->getTarget()));
                    $this->handle($cmd);
                }
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent('preview'));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent('preview', $e->getMessage()));
            }
        }

        if ($collection->hasOptimizeCommand()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent(OptimizeCommand::NAME));
            try {
                $this->handle($collection->getOptimizeCommand()); // @phpstan-ignore argument.type
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent(OptimizeCommand::NAME));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent(OptimizeCommand::NAME, $e->getMessage()));
            }
        }

        if ($collection->hasCompressPackageCommand()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent(CompressPackageCommand::NAME));
            try {
                $this->handle($collection->getCompressPackageCommand()); // @phpstan-ignore argument.type
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent(CompressPackageCommand::NAME));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent(CompressPackageCommand::NAME, $e->getMessage()));
            }
        }

        if ($collection->hasTransferCommand()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent(TransferCommand::NAME));
            try {
                $this->handle($collection->getTransferCommand()); // @phpstan-ignore argument.type
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent(TransferCommand::NAME));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent(TransferCommand::NAME, $e->getMessage()));
            }
        }

        if ($collection->hasCopyBackPreviewCommand()) {
            $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent(CopyBackPreviewCommand::NAME));
            try {
                $this->handle($collection->getCopyBackPreviewCommand()); // @phpstan-ignore argument.type
                $this->eventDispatcher->dispatch(new CommandProcessingStageCompletedEvent(CopyBackPreviewCommand::NAME));
            } catch (\Throwable $e) {
                $this->eventDispatcher->dispatch(new CommandProcessingStageFailedEvent(CopyBackPreviewCommand::NAME, $e->getMessage()));
            }
        }
    }

    private function deleteOutputFolder(): void
    {
        $filesystem = new Filesystem();
        $outputFolder = $this->path->joinWithBase(FolderNames::TEMP->value, 'output');

        if ($filesystem->exists($outputFolder)) {
            $filesystem->remove($outputFolder);
        }

        $tempArtworkPath = $this->path->joinWithBase(
            FolderNames::TEMP->value,
            'artwork_tmp'
        );

        if ($filesystem->exists($tempArtworkPath)) {
            $filesystem->remove($tempArtworkPath);
        }

        $postProcessPath = $this->path->joinWithBase(FolderNames::TEMP->value, 'output', 'post-process');

        if ($filesystem->exists($postProcessPath)) {
            $filesystem->remove($postProcessPath);
        }
    }
}
