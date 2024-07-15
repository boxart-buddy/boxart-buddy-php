<?php

namespace App\Generator;

use App\Config\Processor\ApplicationConfigurationProcessor;
use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Util\File;
use App\Util\Finder;
use App\Util\Path;
use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ConfigFolderGenerator
{
    private ?array $folderAssign = null;

    public function __construct(
        readonly private ConfigReader $configReader,
        readonly private Path $path,
    ) {
    }

    public function generateConfigFolderFile(): void
    {
        $filesystem = new Filesystem();
        $romFolder = $this->configReader->getConfig()->romFolder;

        $finder = new Finder();
        $finder->in($romFolder)->directories();
        // sort by depth
        $finder->sort(static function (\SplFileInfo $a, \SplFileInfo $b) {
            $depth = substr_count($a->getRealPath(), '/') - substr_count($b->getRealPath(), '/');

            return (0 === $depth) ? strcmp($a->getRealPath(), $b->getRealPath()) : $depth;
        });

        $config = [];
        foreach ($finder as $directory) {
            $relativeFolder = Path::remove($directory->getRealPath(), $romFolder);
            $config[$relativeFolder] = $this->getPlatformByFolderName(basename($directory->getRealPath()));
        }

        foreach ($config as $folderName => $platformName) {
            if (null === $platformName) {
                $config[$folderName] = $this->getPlatformByParent($folderName, $config);
            }
        }

        $configFilePath = $this->path->joinWithBase(FolderNames::USER_CONFIG->value, ApplicationConfigurationProcessor::CONFIG_FOLDER_FILENAME);
        $filesystem->dumpFile($configFilePath, Yaml::dump($config, 2, 2, Yaml::DUMP_NULL_AS_TILDE));
        File::addCommentAboveLine($configFilePath, 'Ports: ps5', 'The platform `ps5` for the Ports folder IS CORRECT, do not change the platform to `ports`');
    }

    private function getPlatformByParent(string $folderName, array $config): ?string
    {
        if (!str_contains($folderName, '/')) {
            return null;
        }
        $parent = dirname($folderName);
        if (array_key_exists($parent, $config) && null !== $config[$parent]) {
            return $config[$parent];
        }

        return $this->getPlatformByParent($parent, $config);
    }

    private function getPlatformByFolderName(string $folderName): ?string
    {
        $folderName = strtolower($folderName);
        $assignData = $this->getFolderAssignData();
        if (array_key_exists($folderName, $assignData)) {
            return $assignData[$folderName];
        }

        foreach ($assignData as $assignKey => $platform) {
            if (Str::startsWith($folderName, $assignKey)) {
                return $platform;
            }
        }

        // could also use 'contains' for further matching but
        // better to quit and use parent instead

        return null;
    }

    private function getFolderAssignData(): array
    {
        if ($this->folderAssign) {
            return $this->folderAssign;
        }

        $filesystem = new Filesystem();
        $assignFilePath = $this->path->joinWithBase('resources', 'assign.json');
        json_decode($filesystem->readFile($assignFilePath), true);

        $this->folderAssign = json_decode($filesystem->readFile($assignFilePath), true);

        return $this->folderAssign;
    }
}
