<?php

namespace App\Provider;

use App\FolderNames;
use App\Util\Path;
use Psr\Log\LoggerInterface;

class NamesProvider
{
    private ?array $names = null;

    public function __construct(
        readonly private Path $path,
        readonly private LoggerInterface $logger
    ) {
    }

    public function getNamesInJsonFormat(): string
    {
        $names = $this->getNames();

        try {
            return json_encode($names, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
        } catch (\JsonException $e) {
            $this->logger->error($e->getMessage());

            return '{}';
        }
    }

    public function hasEntriesInNameExtra(): bool
    {
        $extraNamesFilePath = $this->path->joinWithBase(
            FolderNames::USER_CONFIG->value,
            'name_extra.json',
        );

        $ne = file_get_contents($extraNamesFilePath) ?: '{}';

        try {
            $namesExtra = json_decode($ne, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error($e->getMessage());

            return false;
        }

        return count($namesExtra) > 0;
    }

    public function getNames(): array
    {
        if ($this->names) {
            return $this->names;
        }
        $this->loadNames();

        return $this->getNames();
    }

    public function getEntry(string $key): ?string
    {
        $names = $this->getNames();

        return $names[$key] ?? null;
    }

    private function loadNames(): void
    {
        // load all 'names' from resources
        $namesFilePath = $this->path->joinWithBase(
            'resources',
            'name.json',
        );
        $extraNamesFilePath = $this->path->joinWithBase(
            FolderNames::USER_CONFIG->value,
            'name_extra.json',
        );

        $n = file_get_contents($namesFilePath);
        $ne = file_get_contents($extraNamesFilePath);
        if (!$n || !$ne) {
            throw new \RuntimeException('Cannot read names');
        }

        try {
            $names = json_decode($n, true, 512, JSON_THROW_ON_ERROR);
            $namesExtra = json_decode($ne, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error($e->getMessage());

            $this->names = [];

            return;
        }

        $combined = $names + $namesExtra;

        $this->names = $combined;
    }
}
