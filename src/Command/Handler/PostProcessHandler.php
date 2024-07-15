<?php

namespace App\Command\Handler;

use App\Command\CommandInterface;
use App\Command\PostProcessCommand;
use App\PostProcess\BackgroundImagePostProcess;
use App\PostProcess\CounterPostProcess;
use App\PostProcess\InnerMaskPostProcess;
use App\PostProcess\OffsetWithSiblingsPostProcess;
use App\PostProcess\OverlayArtworkGenerationPostProcess;
use App\PostProcess\TextPostProcess;
use App\PostProcess\TranslationPostProcess;
use App\PostProcess\VerticalDotScrollbarPostProcess;
use App\PostProcess\VerticalScrollbarPostProcess;
use Monolog\Attribute\WithMonologChannel;

#[WithMonologChannel('postprocessing')]
readonly class PostProcessHandler implements CommandHandlerInterface
{
    public function __construct(
        private VerticalScrollbarPostProcess $verticalScrollbarPostProcess,
        private VerticalDotScrollbarPostProcess $verticalDotScrollbarPostProcess,
        private BackgroundImagePostProcess $backgroundImagePostProcess,
        private OffsetWithSiblingsPostProcess $offsetWithSiblingsPostProcess,
        private OverlayArtworkGenerationPostProcess $overlayArtworkGenerationPostProcess,
        private TranslationPostProcess $translationPostProcess,
        private CounterPostProcess $counterPostProcess,
        private InnerMaskPostProcess $innerMaskPostProcess,
        private TextPostProcess $textPostProcess,
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof PostProcessCommand) {
            throw new \InvalidArgumentException();
        }

        match ($command->strategy) {
            $this->verticalScrollbarPostProcess->getName() => $this->verticalScrollbarPostProcess->process($command),
            $this->verticalDotScrollbarPostProcess->getName() => $this->verticalDotScrollbarPostProcess->process($command),
            $this->backgroundImagePostProcess->getName() => $this->backgroundImagePostProcess->process($command),
            $this->offsetWithSiblingsPostProcess->getName() => $this->offsetWithSiblingsPostProcess->process($command),
            $this->overlayArtworkGenerationPostProcess->getName() => $this->overlayArtworkGenerationPostProcess->process($command),
            $this->translationPostProcess->getName() => $this->translationPostProcess->process($command),
            $this->counterPostProcess->getName() => $this->counterPostProcess->process($command),
            $this->innerMaskPostProcess->getName() => $this->innerMaskPostProcess->process($command),
            $this->textPostProcess->getName() => $this->textPostProcess->process($command),
            default => throw new \RuntimeException(sprintf('Cannot handle unknown strategy "%s"', $command->strategy))
        };
    }
}
