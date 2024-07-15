<?php

namespace App\Lock;

use App\FolderNames;
use App\Util\Path;
use Symfony\Component\Filesystem\Filesystem;

readonly class LockIO
{
    public const LOCKFILE = 'lock.json';

    public const KEY_ROMFOLDER_HASH = 'romfolder_hash';
    public const KEY_PORTMASTER_LAST_IMPORTED = 'portmaster_last_imported';
    public const KEY_PORTMASTER_LAST_PUBLISHED = 'portmaster_last_published';
    public const KEY_CONFIG_HASH = 'config_hash';
    public const KEY_LAST_RUN_BUILD = 'last_run_build';

    public function __construct(private Path $path)
    {
    }

    private function getLockFilePath(): string
    {
        return $this->path->joinWithBase(FolderNames::TEMP->value, self::LOCKFILE);
    }

    private function initLockFile(): void
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($this->getLockFilePath())) {
            $filesystem->dumpFile($this->getLockFilePath(), '{}');
        }
    }

    private function getLockData(): array
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->getLockFilePath())) {
            $this->initLockFile();
        }

        try {
            return json_decode($filesystem->readFile($this->getLockFilePath()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf('Lock datafile (%s) is malformed, please delete it and retry: `%s`', $this->getLockFilePath(), $e->getMessage()));
        }
    }

    public function writeDateTime(string $key, ?\DateTimeInterface $datetime = null): void
    {
        if (null === $datetime) {
            $datetime = new \DateTimeImmutable();
        }
        $this->write($key, $datetime->format(\DateTimeInterface::ATOM));
    }

    public function readDateTime(string $key): ?\DateTimeInterface
    {
        $datetime = $this->read($key);
        if (!$datetime) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat(
            \DateTimeInterface::ATOM,
            $datetime
        ) ?: null;
    }

    public function write(string $key, mixed $value): void
    {
        $lockData = $this->getLockData();
        $lockData[$key] = $value;

        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->getLockFilePath(), json_encode($lockData, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT) ?: '{}');
    }

    public function read(string $key): mixed
    {
        $data = $this->getLockData();

        return $data[$key] ?? null;
    }
}
