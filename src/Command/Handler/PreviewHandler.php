<?php

namespace App\Command\Handler;

use App\Command\CommandInterface;
use App\Command\PreviewCommand;
use App\Preview\PreviewGenerator;

readonly class PreviewHandler implements CommandHandlerInterface
{
    public function __construct(
        private PreviewGenerator $previewGenerator,
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof PreviewCommand) {
            throw new \InvalidArgumentException();
        }
        if (in_array($command->previewType, ['animated', 'both'])) {
            $this->previewGenerator->generateAnimatedPreview($command->target, $command->previewName);
        }
        if (in_array($command->previewType, ['static', 'both'])) {
            $this->previewGenerator->generateStaticPreview($command->target, $command->previewName);
        }
    }
}
