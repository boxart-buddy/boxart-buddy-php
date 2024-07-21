<?php

namespace App\ConsoleCommand;

use App\Skyscraper\CacheDownloader;
use App\Util\Console\BlockSectionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;

#[AsCommand(
    name: 'download-cache',
    description: 'Downloads a pre scraped skyscraper cache',
)]
class DownloadCacheCommand extends Command
{
    public function __construct(private readonly CacheDownloader $cacheDownloader)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output);

        $variant = (string) select(
            'Select Cache Download',
            ['small' => 'Small Cache (711MB) ~ 2200 Roms'],
            'small',
            4
        );

        $io->section('info');
        $io->wait('Downloading & Replacing Cache');
        $this->cacheDownloader->download($variant);
        $io->done('Download & Cache Replacement Complete');

        return Command::SUCCESS;
    }
}
