<?php

namespace App\Generator;

use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Provider\PathProvider;
use App\Util\Path;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Creates rom import data files for skipped roms, namespaced by romset.
 */
readonly class SkippedRomImportDataGenerator
{
    public const ROM_MISSING_JSON = 'missing.json';

    public function __construct(
        private ConfigReader $configReader,
        private Path $path,
        private PathProvider $pathProvider,
        private ManualImportXMLGenerator $manualImportXMLGenerator,
        private LoggerInterface $logger
    ) {
    }

    public function generate(string $romAbsolutePath, bool $idempotent = false): void
    {
        $this->createSkippedRomImportData($romAbsolutePath);

        if (!$idempotent) {
            $this->deleteSkippedCacheFiles();
        }
    }

    public function resetMissingRomFile(): void
    {
        $romset = $this->configReader->getConfig()->romsetName;
        $missingJsonPath = $this->path->joinWithBase(FolderNames::SKIPPED->value, $romset, self::ROM_MISSING_JSON);
        $filesystem = new Filesystem();
        $filesystem->dumpFile($missingJsonPath, '[]');
    }

    public function deleteSkippedCacheFiles(): void
    {
        $skyscraperConfigFolder = $this->configReader->getConfig()->skyscraperConfigFolderPath;
        $filesystem = new Filesystem();
        $finder = new Finder();
        $finder->in(Path::join($skyscraperConfigFolder));
        $finder->files()->name('skipped-*-cache.txt')->depth(0);
        foreach ($finder as $file) {
            $filesystem->remove($file->getRealPath());
        }
    }

    private function createSkippedRomImportData(string $romAbsolutePath): void
    {
        $config = $this->configReader->getConfig();
        $skyscraperConfigFolder = $config->skyscraperConfigFolderPath;
        $romset = $config->romsetName;
        $filesystem = new Filesystem();

        // get files from SS directory
        $finder = new Finder();
        $finder->in(Path::join($skyscraperConfigFolder));
        $finder->files()->name('skipped-*-cache.txt')->depth(0);

        if (!$finder->hasResults()) {
            return;
        }

        $skippedBase = $this->path->joinWithBase(FolderNames::SKIPPED->value, $romset);

        foreach ($finder as $file) {
            $filename = $file->getBasename();
            if (preg_match('/skipped-(.*?)-cache\.txt/', $filename, $matches)) {
                $platform = $matches[1];
            } else {
                throw new \RuntimeException();
            }

            $platformSkippedBase = Path::join($skippedBase, $this->pathProvider->removeRomFolderBase(dirname($romAbsolutePath)));

            // read the file and iterate it line by line
            $fileObject = $file->openFile();
            while (!$fileObject->eof()) {
                $romPath = trim($fileObject->fgets());
                if (!$romPath) {
                    continue;
                }
                $title = basename($romPath, '.'.pathinfo($romPath, PATHINFO_EXTENSION));

                // text
                $this->manualImportXMLGenerator->generateXML(
                    Path::join($platformSkippedBase, 'textual', $title.'.xml'),
                    $title,
                    'Add a description',
                    null,
                    true
                );

                // screenshot
                $filesystem->copy(
                    $this->path->joinWithBase('resources', 'missing.png'),
                    Path::join($platformSkippedBase, 'screenshots', $title.'.png'),
                );

                // cover
                $filesystem->copy(
                    $this->path->joinWithBase('resources', 'missing.png'),
                    Path::join($platformSkippedBase, 'covers', $title.'.png'),
                );

                // wheels
                $filesystem->copy(
                    $this->path->joinWithBase('resources', 'missing-logo.png'),
                    Path::join($platformSkippedBase, 'wheels', $title.'.png'),
                );

                // add to report
                $this->addToMissingRomFile($romset, $romPath, $platform);
            }
        }
    }

    private function addToMissingRomFile(string $romset, string $romAbsolutePath, string $platform): void
    {
        $filesystem = new Filesystem();
        $missingJsonPath = $this->path->joinWithBase(FolderNames::SKIPPED->value, $romset, self::ROM_MISSING_JSON);
        $missingAlready = [];

        try {
            if ($filesystem->exists($missingJsonPath)) {
                $missingAlready = json_decode($filesystem->readFile($missingJsonPath), true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (\JsonException $e) {
            $this->logger->critical('The missing.json file is malformed. If you have edited it manually please ensure the file contains valid json');
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getFile());

            return;
        }

        // already added
        if (array_key_exists($romAbsolutePath, $missingAlready)) {
            return;
        }

        $missingAlready[$romAbsolutePath] = ['platform' => $platform, 'query' => 'crc='];

        $filesystem->dumpFile($missingJsonPath, json_encode($missingAlready, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT) ?: '{}');
    }
}
