<?php

namespace App\Util;

use App\Config\Reader\ConfigReader;
use Symfony\Component\Filesystem\Path as SymfonyPath;

readonly class FirstExistingResourceHelper
{
    public function __construct(private ConfigReader $configReader)
    {
    }

    public function path(string ...$filenames): string
    {
        foreach ($filenames as $filename) {
            $path = Path::join(SymfonyPath::canonicalize($this->configReader->getConfig()->skyscraperConfigFolderPath), 'resources', $filename);
            if (file_exists($path)) {
                return $filename;
            }
        }

        return '';
    }
}
