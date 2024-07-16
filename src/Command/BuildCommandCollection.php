<?php

namespace App\Command;

// A collection of all commands to build a set of artwork and take subsequent actions
class BuildCommandCollection
{
    public function __construct(
        public string $id,
        private ?CopyResourcesCommand $copyResourcesCommand = null,
        private ?PackageCommand $packageCommand = null,
        private ?OptimizeCommand $optimizeCommand = null,
        private ?CompressPackageCommand $compressPackageCommand = null,
        private ?TransferCommand $transferCommand = null,
        private ?CopyBackPreviewCommand $copyBackPreviewCommand = null,
        private array $generateArtworkCommands = [],
        private array $generateEmptyImageCommands = [],
        private array $postProcessCommands = [],
        private array $previewCommands = [],
    ) {
    }

    public function getPackageCommand(): ?PackageCommand
    {
        return $this->packageCommand;
    }

    public function getOptimizeCommand(): ?OptimizeCommand
    {
        return $this->optimizeCommand;
    }

    public function getCompressPackageCommand(): ?CompressPackageCommand
    {
        return $this->compressPackageCommand;
    }

    public function getTransferCommand(): ?TransferCommand
    {
        return $this->transferCommand;
    }

    public function getCopyBackPreviewCommand(): ?CopyBackPreviewCommand
    {
        return $this->copyBackPreviewCommand;
    }

    public function getGenerateArtworkCommands(): array
    {
        return $this->generateArtworkCommands;
    }

    public function getGenerateEmptyImageCommands(): array
    {
        return $this->generateEmptyImageCommands;
    }

    public function hasGenerateArtworkCommands(): bool
    {
        return !empty($this->generateArtworkCommands);
    }

    public function hasGenerateEmptyImageCommands(): bool
    {
        return !empty($this->generateEmptyImageCommands);
    }

    public function getPostProcessCommands(): array
    {
        return $this->postProcessCommands;
    }

    public function hasPostProcessCommands(): bool
    {
        return !empty($this->postProcessCommands);
    }

    public function getPreviewCommands(): array
    {
        return $this->previewCommands;
    }

    public function hasPreviewCommands(): bool
    {
        return !empty($this->previewCommands);
    }

    public function getCopyResourcesCommand(): ?CopyResourcesCommand
    {
        return $this->copyResourcesCommand;
    }

    public function hasCopyResourcesCommand(): bool
    {
        return null !== $this->copyResourcesCommand;
    }

    public function setCopyResourcesCommand(CopyResourcesCommand $copyResourcesCommand): void
    {
        $this->copyResourcesCommand = $copyResourcesCommand;
    }

    public function setPackageCommand(PackageCommand $packageCommand): void
    {
        $this->packageCommand = $packageCommand;
    }

    public function hasPackageCommand(): bool
    {
        return null !== $this->packageCommand;
    }

    public function setOptimizeCommand(OptimizeCommand $optimizeCommand): void
    {
        $this->optimizeCommand = $optimizeCommand;
    }

    public function hasOptimizeCommand(): bool
    {
        return null !== $this->optimizeCommand;
    }

    public function setCompressPackageCommand(CompressPackageCommand $compressPackageCommand): void
    {
        $this->compressPackageCommand = $compressPackageCommand;
    }

    public function hasCompressPackageCommand(): bool
    {
        return null !== $this->compressPackageCommand;
    }

    public function setTransferCommand(TransferCommand $transferCommand): void
    {
        $this->transferCommand = $transferCommand;
    }

    public function hasTransferCommand(): bool
    {
        return null !== $this->transferCommand;
    }

    public function setCopyBackPreviewCommand(CopyBackPreviewCommand $copyBackPreviewCommand): void
    {
        $this->copyBackPreviewCommand = $copyBackPreviewCommand;
    }

    public function hasCopyBackPreviewCommand(): bool
    {
        return null !== $this->copyBackPreviewCommand;
    }

    public function setGenerateArtworkCommands(array $generateArtworkCommands): void
    {
        $this->generateArtworkCommands = $generateArtworkCommands;
    }

    public function setGenerateEmptyImageCommands(array $commands): void
    {
        $this->generateEmptyImageCommands = $commands;
    }

    public function addPostProcessCommands(array $postProcessCommands): void
    {
        $this->postProcessCommands = array_merge($this->postProcessCommands, $postProcessCommands);
    }

    public function setPostProcessCommands(array $postProcessCommands): void
    {
        $this->postProcessCommands = $postProcessCommands;
    }

    public function setPreviewCommands(array $previewCommands): void
    {
        $this->previewCommands = $previewCommands;
    }
}
