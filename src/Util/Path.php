<?php

namespace App\Util;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path as SymfonyPath;

readonly class Path
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $basePath,
    ) {
    }

    public function joinWithBase(string ...$pathParts): string
    {
        array_unshift($pathParts, $this->basePath);

        return call_user_func_array([__CLASS__, 'join'], $pathParts);
    }

    public function removeBase(string $absolutePath): string
    {
        return $this->remove($absolutePath, $this->basePath);
    }

    public static function remove(string $path, string $toRemove): string
    {
        $toRemove = ltrim($toRemove, '/');

        return ltrim(str_replace($toRemove, '', $path), '/');
    }

    public static function join(string ...$pathParts): string
    {
        $pattern = '#(/)+#';

        return SymfonyPath::canonicalize(
            (string) preg_replace($pattern, '/', join('/', $pathParts))
        );
    }

    public static function removeExtension(string $filename): string
    {
        return preg_replace('/\.[^.]*$/', '', $filename) ?? $filename;
    }

    public static function getDirectorySize(string $directory): string
    {
        $size = self::getOneDirectorySize(SymfonyPath::canonicalize($directory));

        return self::formatBytes($size);
    }

    private static function getOneDirectorySize(string $directory): int
    {
        $size = 0;

        $paths = glob(rtrim($directory, '/').'/*', GLOB_NOSORT);
        if (false === $paths) {
            return 0;
        }
        foreach ($paths as $each) {
            $size += is_file($each) ? filesize($each) : self::getOneDirectorySize($each);
        }

        return $size;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes > 1000 * 1000) {
            return round($bytes / 1000 / 1000, 2).' MB';
        } elseif ($bytes > 1000) {
            return round($bytes / 1000, 2).' KB';
        }

        return $bytes.' B';
    }

    public static function prettifyFilename(string $filename): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($filename)) ?? $filename;
    }

    public static function pathToDash(string $path): string
    {
        return preg_replace('#/#', '-', $path) ?? $path;
    }

    public static function hashForDirectoryContents(string $path, bool $directoriesOnly = false): string
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($path)) {
            throw new \RuntimeException(sprintf('folder "%s" does not exist', $path));
        }

        $finder = new Finder();
        $finder->in($path);
        if ($directoriesOnly) {
            $finder->directories();
        }

        $hashString = '';
        foreach ($finder as $entry) {
            $hashString .= $entry->getFilename();
        }

        return hash('xxh3', $hashString);
    }
}
