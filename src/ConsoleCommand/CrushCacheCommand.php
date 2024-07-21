<?php

namespace App\ConsoleCommand;

use App\Skyscraper\CacheCrusher;
use App\Skyscraper\CacheCrushReport;
use App\Util\Console\BlockSectionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'crush-cache',
    description: 'Compresses the skyscraper cache by resizing images and/or lossless size reducing',
)]
class CrushCacheCommand extends Command
{
    public function __construct(private CacheCrusher $cacheCrusher, private CacheCrushReport $crushReport)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output);

        $io->section('crush-preface');
        $io->wait(sprintf('Cache Size Before: %s', $this->crushReport->getCacheSize()));

        $this->cacheCrusher->crush();
        $this->cacheCrusher->quickidXmlToEmpty();

        $io->complete(sprintf(
            'Completed Cache Crushing. Processed %s files. %s Resized',
            $this->crushReport->getCount(),
            $this->crushReport->getResizedCount()
        ));

        $io->section('crush-finale');
        $io->wait(sprintf('Cache Size After: %s', $this->crushReport->getCacheSize()));

        return Command::SUCCESS;
    }
}
