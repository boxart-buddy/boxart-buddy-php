<?php

namespace App\ConsoleCommand;

use App\Hugo\HugoResourceCreator;
use App\Util\Console\BlockSectionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'template-to-hugo',
    description: 'Copies template resources to locations they can be used by hugo',
)]
class TemplateToHugoCommand extends Command
{
    public function __construct(
        readonly private HugoResourceCreator $hugoResourceCreator,
        readonly private \App\Util\Path $path
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        if (Path::isRelative($path)) {
            $path = $this->path->joinWithBase($path);
        }

        $path = Path::canonicalize($path);

        var_dump($path);

        $io = new BlockSectionHelper($input, $output);
        $io->heading();

        $this->hugoResourceCreator->copyTemplatePreviewsToStatic($path);
        $this->hugoResourceCreator->createHugoDataFixtureForTemplates($path);
        $this->hugoResourceCreator->createHugoDataFixtureForThemes($path);

        $io->complete('Complete');

        return Command::SUCCESS;
    }
}
