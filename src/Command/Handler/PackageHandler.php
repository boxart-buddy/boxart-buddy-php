<?php

namespace App\Command\Handler;

use App\Command\CommandInterface;
use App\Command\PackageCommand;
use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Provider\NamesProvider;
use App\Provider\PathProvider;
use App\Provider\PlatformProvider;
use App\Util\Finder;
use App\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

readonly class PackageHandler implements CommandHandlerInterface
{
    public function __construct(
        private ConfigReader $configReader,
        private Path $path,
        private PathProvider $pathProvider,
        private NamesProvider $namesProvider,
        private PlatformProvider $platformProvider
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof PackageCommand) {
            throw new \InvalidArgumentException();
        }

        $config = $this->configReader->getConfig();
        $filesystem = new Filesystem();

        $outputFolder = $this->path->joinWithBase(FolderNames::TEMP->value, 'output', 'generated_artwork');

        $packageBase = $this->pathProvider->getPackageRootPath($command->packageName);

        // wipe package folder
        if ($filesystem->exists($packageBase)) {
            $filesystem->remove($packageBase);
        }

        $packageOut = Path::join(
            $packageBase,
            'MUOS',
            'info',
            'catalogue',
        );

        $finder = new Finder();

        $finder->in($outputFolder);
        $finder->directories();

        foreach ($finder as $directory) {
            if (in_array($directory->getBasename(), ['covers', 'screenshots', 'txt', 'textures', 'marquees', 'wheels'])) {
                continue;
            }
            $platform = $this->platformProvider->getPlatform(
                $directory->getRealPath()
            );
            // $config->getPlatformForFolder(basename($directory->getRealPath()));

            $innerFinder = new Finder();
            $innerFinder->in($directory)->directories()->depth(0)->path(['covers', 'screenshots', 'txt']);

            foreach ($innerFinder as $innerDirectory) {
                $folderRewrite = match (basename($innerDirectory->getRealPath())) {
                    'covers' => 'box',
                    'screenshots' => 'preview',
                    'txt' => 'text',
                    default => throw new \RuntimeException(),
                };

                $packageFolder = $config->getPackageFolderForPlatform($platform);

                $filesystem->mirror(
                    $innerDirectory->getRealPath(),
                    Path::join($packageOut, $packageFolder, $folderRewrite)
                );
            }
        }

        // DO PORTMASTER
        //        $finder = new Finder();
        //        $pattern = '#^(portmaster)\/generated_artwork\/[^\/]+\/(covers|screenshots|txt)#';
        //
        //        $finder->in($outputFolder);
        //        $finder->path($pattern)->directories();
        //
        //        foreach ($finder as $directory) {
        //            $folder = basename($directory->getRealPath());
        //            $folderRewrite = match ($folder) {
        //                'covers' => 'box',
        //                'screenshots' => 'preview',
        //                'txt' => 'text',
        //                default => throw new \RuntimeException(),
        //            };
        //
        //            $filesystem->mirror(
        //                $directory->getRealPath(),
        //                Path::join($packageOut, 'External - Ports', $folderRewrite)
        //            );
        //        }

        $this->addNotes($packageBase);
        $this->addNames($packageBase);
    }

    private function addNames(string $packageBase): void
    {
        if (!$this->namesProvider->hasEntriesInNameExtra()) {
            return;
        }
        $filesystem = new Filesystem();
        // providing both name.ini and name.json to support non refried beans, to remove ini in future
        $namePath = Path::join($packageBase, 'MUOS', 'info', 'name.json');
        $filesystem->appendToFile($namePath, $this->namesProvider->getNamesInJsonFormat());
    }

    private function addNotes(string $packageBase): void
    {
        $filesystem = new Filesystem();

        $noteFilename = Path::join($packageBase, 'extra', 'notes.txt');

        // write note header
        if (!$filesystem->exists($noteFilename)) {
            $noteHeader = "Generated with Boxart Buddy (https://github.com/boxart-buddy/boxart-buddy/) \n\n";
            // check if LASTRUNCHOICES.json is set and add it to the output
            $lastRunFile = $this->path->joinWithBase(FolderNames::TEMP->value, 'LASTRUNCHOICES.json');

            if ($filesystem->exists($lastRunFile)) {
                $noteHeader = $noteHeader."Generated with the following choices \n";
                $json = json_decode($filesystem->readFile($lastRunFile), true);
                $noteHeader = $noteHeader.json_encode($json, JSON_PRETTY_PRINT)."\n\n";
            }
            $filesystem->appendToFile($noteFilename, $noteHeader);
        }

        // read existing notes and concat them into the notes.txt file
        $noteFolder = $this->path->joinWithBase(
            FolderNames::TEMP->value, 'output', 'notes'
        );

        if (!$filesystem->exists($noteFolder)) {
            return;
        }

        $writtenNotes = [];
        $finder = new Finder();
        $finder->in($noteFolder)->files()->name('*.txt');
        foreach ($finder as $file) {
            $noteContents = $filesystem->readFile($file->getRealPath());
            $writeKey = hash('xxh3', $noteContents);
            if (in_array($writeKey, $writtenNotes)) {
                return;
            }
            $writtenNotes[] = $writeKey;
            $filesystem->appendToFile($noteFilename, $noteContents);
        }
    }
}
