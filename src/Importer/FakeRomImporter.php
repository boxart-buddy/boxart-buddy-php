<?php

namespace App\Importer;

use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Generator\ManualImportXMLGenerator;
use App\Provider\PathProvider;
use App\Util\Path;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Filesystem\Filesystem;

#[WithMonologChannel('skyscraper')]
readonly class FakeRomImporter
{
    public const FAKE_ROM_NAME = 'fake-rom';

    public function __construct(
        private Path $path,
        private ConfigReader $configReader,
        private PathProvider $pathProvider,
        private SkyscraperManualDataImporter $skyscraperManualDataImporter,
        private ManualImportXMLGenerator $manualImportXMLGenerator,
    ) {
    }

    public function import(): void
    {
        $this->makeFakeRoms();
        $this->createEmptyResourcesInImportLocation();
        $this->importAll();
    }

    private function makeFakeRoms(): void
    {
        $fakeRomPath = $this->pathProvider->getFakeRomPath();

        $filesystem = new Filesystem();

        if ($filesystem->exists($fakeRomPath)) {
            $filesystem->remove($fakeRomPath);
        }

        foreach ($this->configReader->getConfig()->package as $platform => $packageFolder) {
            $filesystem->appendToFile(Path::join($fakeRomPath, $platform, self::FAKE_ROM_NAME.'.zip'), 'fake');
        }
    }

    private function createEmptyResourcesInImportLocation(): void
    {
        $filesystem = new Filesystem();
        foreach ($this->configReader->getConfig()->package as $platform => $packageFolder) {
            $importBase = $this->path->joinWithBase(FolderNames::TEMP->value, 'fake_roms', 'import', $platform);

            $filesystem->copy(
                $this->path->joinWithBase('resources', 'null.png'),
                Path::join($importBase, 'screenshot', self::FAKE_ROM_NAME.'.png')
            );

            $txtPath = Path::join($importBase, 'textual', self::FAKE_ROM_NAME.'.txt');
            $this->manualImportXMLGenerator->generateXML($txtPath, 'Fake', 'fake');
        }
    }

    private function importAll(): void
    {
        foreach ($this->configReader->getConfig()->package as $platform => $packageFolder) {
            $this->skyscraperManualDataImporter->importResources(
                $this->path->joinWithBase(FolderNames::TEMP->value, 'fake_roms', 'import', $platform),
                $platform,
                $this->path->joinWithBase(FolderNames::TEMP->value, 'fake_roms', 'roms', $platform)
            );
        }
    }
}
