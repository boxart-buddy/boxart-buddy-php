<?php

namespace App\Command\Handler;

use App\Builder\SkyscraperCommandDirector;
use App\Command\CommandInterface;
use App\Command\PrimeCacheCommand;
use App\Provider\PathProvider;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

#[WithMonologChannel('skyscraper')]
readonly class PrimeCacheHandler implements CommandHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private SkyscraperCommandDirector $skyscraperCommandDirector,
        private PathProvider $pathProvider
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof PrimeCacheCommand) {
            throw new \InvalidArgumentException();
        }

        $command = $this->skyscraperCommandDirector->getScrapeCommand(
            $command->folderAbsolutePath,
            $command->onlyMissing
        );

        $process = new Process($command);
        $process->setTimeout(60 * 60 * 6);

        try {
            $process->run();

            $output = $process->getOutput();
            $this->logger->debug($output);
            if (!$process->isSuccessful()) {
                throw new \RuntimeException(sprintf('The scraping process failed. Check `%s`', $this->pathProvider->getLogPath('skyscraper')));
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \RuntimeException(sprintf('The scraping process failed. Check `%s`', $this->pathProvider->getLogPath('skyscraper')));
        }
    }
}
