<?php

namespace App\Command\Handler;

use App\Command\CommandInterface;
use App\Command\CommandNamespace;
use App\Command\CompressPackageCommand;
use App\FolderNames;
use App\Provider\PathProvider;
use App\Util\Path;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

readonly class CompressPackageHandler implements CommandHandlerInterface
{
    public function __construct(
        private PathProvider $pathProvider,
        private Path $path,
        private LoggerInterface $logger
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof CompressPackageCommand) {
            throw new \InvalidArgumentException();
        }

        $packagePath = Path::join($this->pathProvider->getPackageRootPath($command->packageName), 'MUOS');

        $outPath = $this->pathProvider->getPackageZipPath($command->packageName);

        $filesystem = new Filesystem();
        if ($filesystem->exists($outPath)) {
            $filesystem->remove($outPath);
        }

        $zip = new ZipFile();

        $shouldAddNukeScript = in_array(true, $command->nukeOptions, true);

        if ($shouldAddNukeScript) {
            $folderCleanScriptPath = $this->path->joinWithBase(FolderNames::TEMP->value, 'update.sh');
            $filesystem->dumpFile($folderCleanScriptPath, $this->generateNukeUpdateSh($command->nukeOptions));
        }

        try {
            $zip->addDirRecursive($packagePath, '/mnt/mmc/MUOS', ZipCompressionMethod::DEFLATED);

            if ($shouldAddNukeScript) {
                $zip->addFile($folderCleanScriptPath, '/opt/update.sh');
            }

            $zip->saveAsFile($outPath);
        } catch (ZipException $e) {
            $this->logger->error($e->getMessage());
        }
    }

    private function generateNukeUpdateSh(array $nukeOptions): string
    {
        $update = ['rm -rf /mnt/mmc/extra'];
        foreach ($nukeOptions as $namespace => $shouldNuke) {
            if (!$shouldNuke) {
                continue;
            }
            $update[] = match ($namespace) {
                CommandNamespace::ARTWORK->value => "find '/mnt/mmc/MUOS/info/catalogue' -type f -mmin -10 -print0 -o -path '/mnt/mmc/MUOS/info/catalogue/Folder' -prune -o -path '/mnt/mmc/MUOS/info/catalogue/External - Ports' -prune | xargs -0 rm",
                CommandNamespace::PORTMASTER->value => "find '/mnt/mmc/MUOS/info/catalogue/External - Ports' -type f -mmin -10 -print0 | xargs -0 rm",
                CommandNamespace::FOLDER->value => "find '/mnt/mmc/MUOS/info/catalogue/Folder' -type f -mmin -10 -print0 | xargs -0 rm",
                default => throw new \InvalidArgumentException()
            };
        }

        return implode("\n", $update);
    }
}
