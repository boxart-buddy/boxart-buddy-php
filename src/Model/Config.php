<?php

namespace App\Model;

use Symfony\Component\PropertyAccess\PropertyAccess;

readonly class Config
{
    public static function fromArray(array $c): Config
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        return new self(
            $c['rom_folder'],
            $c['romset_name'],
            $c['screenscraper_user'],
            $c['screenscraper_pass'],
            $c['skyscraper_config_folder_path'],
            $c['skyscraper_cache_folder_path'],
            $c['folders'],
            $c['package'],
            $c['folder_roms'],
            $c['portmaster'],
            $c['portmaster_generate_all'],
            $c['portmaster_alternates'],
            $propertyAccessor->getValue($c, '[optimize][enabled]'),
            $propertyAccessor->getValue($c, '[optimize][convert_to_jpg]'),
            $propertyAccessor->getValue($c, '[optimize][jpg_quality]'),
            $propertyAccessor->getValue($c, '[preview][type]'),
            $propertyAccessor->getValue($c, '[preview][grid_size]'),
            $propertyAccessor->getValue($c, '[preview][animation_frames]'),
            $propertyAccessor->getValue($c, '[preview][animation_format]'),
            $propertyAccessor->getValue($c, '[preview][theme]'),
            $propertyAccessor->getValue($c, '[preview][copy_back]'),
            $propertyAccessor->getValue($c, '[sftp?][ip]'),
            $propertyAccessor->getValue($c, '[sftp?][user]'),
            $propertyAccessor->getValue($c, '[sftp?][pass]'),
            $propertyAccessor->getValue($c, '[sftp?][port]'),
        );
    }

    public function __construct(
        public string $romFolder,
        public string $romsetName,
        private string $screenScraperUser,
        private string $screenScraperPassword,
        public string $skyscraperConfigFolderPath,
        public string $skyscraperCacheFolderPath,
        public array $folders,
        public array $package,
        public array $folderRoms,
        public array $portmaster,
        public bool $portmasterGenerateAll,
        public array $portmasterAlternates,
        public bool $shouldOptimize,
        public bool $convertToJpg,
        public int $jpgQuality,
        public string $previewType,
        public int $previewGridSize,
        public int $animationFrames,
        public string $animationFormat,
        public array $previewThemes,
        public bool $copyPreviewBackToTemplate,
        public ?string $sftpIp,
        public ?string $sftpUser,
        public ?string $sftpPass,
        public ?string $sftpPort,
    ) {
    }

    public function getScreenScraperCredentials(): string
    {
        return sprintf(
            '%s:%s',
            $this->screenScraperUser,
            $this->screenScraperPassword
        );
    }

    public function getPackageFolderForPlatform(string $platform): string
    {
        if ('folder' === strtolower($platform)) {
            return 'Folder';
        }
        if ('ports' === strtolower($platform)) {
            return 'External - Ports';
        }
        if (!array_key_exists($platform, $this->package)) {
            throw new \InvalidArgumentException(sprintf('Platform "%s" does not exist in the package mapping.', $platform));
        }

        return $this->package[$platform];
    }

    public function getPlatformForFolder(string $folder): ?string
    {
        if (!array_key_exists($folder, $this->folders)) {
            return null;
        }

        return $this->folders[$folder];
    }

    public function getSingleRomForFolder(string $folder): ?string
    {
        if (isset($this->folderRoms[$folder])) {
            return $this->folderRoms[$folder];
        }

        return null;
    }

    public function getPortmasterAlternatePlatform(string $name): ?string
    {
        return $this->portmasterAlternates[$name]['platform'] ?? null;
    }
}
