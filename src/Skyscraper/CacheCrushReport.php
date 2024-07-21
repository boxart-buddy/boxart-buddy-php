<?php

namespace App\Skyscraper;

use App\Config\Reader\ConfigReader;
use App\Util\Path;

class CacheCrushReport
{
    private int $count = 0;
    private int $resizedCount = 0;

    public function __construct(readonly private ConfigReader $configReader)
    {
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function addToResizeCount(): void
    {
        ++$this->resizedCount;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getResizedCount(): int
    {
        return $this->resizedCount;
    }

    public function getCacheSize(): string
    {
        return Path::getDirectorySize($this->configReader->getConfig()->skyscraperCacheFolderPath);
    }
}
