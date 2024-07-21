<?php

namespace App\Skyscraper;

use App\Config\Reader\ConfigReader;
use App\Event\CommandProcessingStageProgressedEvent;
use App\Event\CommandProcessingStageStartedEvent;
use App\Util\Path;
use PhpZip\ZipFile;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path as SymfonyPath;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class CacheDownloader
{
    public const SMALL_CACHE_URL = 'https://onedrive.live.com/download?resid=60C7045B43315384%21345&authkey=!AKaOShd-rUgXr98';

    public function __construct(
        private ConfigReader $configReader,
        private HttpClientInterface $client,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function download(string $type): void
    {
        $filesystem = new Filesystem();

        $url = match ($type) {
            'small' => self::SMALL_CACHE_URL,
            default => throw new \InvalidArgumentException()
        };

        $this->eventDispatcher->dispatch(new CommandProcessingStageStartedEvent('download', true));

        $response = $this->client->request('GET', $url, [
            'on_progress' => function (int $dlNow, int $dlSize, array $info): void {
                if (0 == $dlSize || 0 == $dlNow) {
                    return;
                }

                $this->eventDispatcher->dispatch(
                    new CommandProcessingStageProgressedEvent(
                        'download',
                        sprintf('%s%%', floor($dlNow * 100 / $dlSize))
                    )
                );
            },
        ]);

        $tmpPath = tempnam(sys_get_temp_dir(), 'cache.zip');
        $fileHandler = fopen($tmpPath, 'w');

        $cacheFolderPath = $this->configReader->getConfig()->skyscraperCacheFolderPath;
        $cacheParentFolderPath = SymfonyPath::canonicalize($cacheFolderPath.'/..');

        foreach ($this->client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }

        $zipFile = new ZipFile();

        $backupCachePath = Path::Join($cacheParentFolderPath, 'cache_bak_'.hash('xxh3', (string) mt_rand()));

        if ($filesystem->exists($cacheFolderPath)) {
            $filesystem->rename($cacheFolderPath, $backupCachePath);
        }

        $zipFile
            ->openFile($tmpPath)
            ->extractTo($cacheParentFolderPath);
    }
}
