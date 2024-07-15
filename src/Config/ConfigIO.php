<?php

namespace App\Config;

// Only interacts with already created .yml files inside user_config folder
use App\FolderNames;
use App\Util\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

readonly class ConfigIO
{
    public function __construct(private Path $path)
    {
    }

    public function read(string $file, string $key): mixed
    {
        $data = $this->getData($file);

        return $data[$key] ?? null;
    }

    // Note that using this function will strip any comments etc from an existing yaml file
    public function write(string $file, string $key, mixed $value): void
    {
        $data = $this->getData($file);
        $data[$key] = $value;

        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            $this->getConfigFilePath($file),
            Yaml::dump($data, 2, 2, Yaml::DUMP_NULL_AS_TILDE)
        );
    }

    private function getData(string $file): array
    {
        $filesystem = new Filesystem();

        $configPath = $this->getConfigFilePath($file);
        if (!$filesystem->exists($configPath)) {
            throw new \RuntimeException(sprintf('Config file at "%s" does not exist', $configPath));
        }

        return Yaml::parse($filesystem->readFile($configPath));
    }

    private function getConfigFilePath(string $file): string
    {
        return $this->path->joinWithBase(FolderNames::USER_CONFIG->value, $file);
    }
}
