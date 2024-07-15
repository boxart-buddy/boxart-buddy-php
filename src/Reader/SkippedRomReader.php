<?php

namespace App\Reader;

use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Generator\SkippedRomImportDataGenerator;
use App\Util\Path;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

readonly class SkippedRomReader
{
    public function __construct(
        private Path $path,
        private ConfigReader $configReader,
        private LoggerInterface $logger
    ) {
    }

    public function getSkippedRomCount(): int
    {
        $data = $this->getSkippedRomData();

        return count($data);
    }

    public function getSkippedRomData(): array
    {
        $filesystem = new Filesystem();
        $romsetName = $this->configReader->getConfig()->romsetName;

        $missingJsonPath = $this->path->joinWithBase(FolderNames::SKIPPED->value, $romsetName, SkippedRomImportDataGenerator::ROM_MISSING_JSON);
        if (!$filesystem->exists($missingJsonPath)) {
            return [];
        }

        try {
            return json_decode($filesystem->readFile($missingJsonPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->critical('The missing.json file is malformed. If you have edited it manually please ensure the file contains valid json');
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getFile());
            throw new \RuntimeException('The missing.json file is malformed. If you have edited it manually please ensure the file contains valid json');
        }
    }
}
