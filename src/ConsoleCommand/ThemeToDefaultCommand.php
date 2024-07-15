<?php

namespace App\ConsoleCommand;

use App\Command\Processor\ThemeToDefaultProcessor;
use App\Util\Console\BlockSectionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'theme-to-default',
    description: 'Read theme files and create theme-default entries for every one',
)]
class ThemeToDefaultCommand extends Command
{
    public function __construct(readonly private ThemeToDefaultProcessor $themeToDefaultProcessor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output);

        $this->themeToDefaultProcessor->process();

        $io->complete('Import complete');

        return Command::SUCCESS;
    }
}
