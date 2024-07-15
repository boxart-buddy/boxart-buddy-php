<?php

namespace App\Command\Handler;

use App\Command\CommandInterface;
use App\Command\CommandNamespace;
use App\Command\GenerateEmptyImageCommand;
use App\Provider\PathProvider;
use App\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

readonly class GenerateEmptyImageHandler implements CommandHandlerInterface
{
    public function __construct(
        private PathProvider $pathProvider,
        private Path $path,
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof GenerateEmptyImageCommand) {
            throw new \InvalidArgumentException();
        }

        $filesystem = new Filesystem();

        $outFolder = $this->pathProvider->getOutputPathForGeneratedArtworkForNamespace($command->absolutePath, $command->isDir() ? CommandNamespace::FOLDER : CommandNamespace::ARTWORK);

        $outPath = Path::join(
            $outFolder,
            'covers',
            Path::removeExtension(basename($command->absolutePath)).'.png'
        );

        $filesystem->copy(
            $this->path->joinWithBase('resources', 'null.png'),
            $outPath
        );
    }
}
