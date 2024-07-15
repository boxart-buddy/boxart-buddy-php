<?php

namespace App\ConsoleCommand;

use App\FolderNames;
use App\Util\Console\BlockSectionHelper;
use App\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'delete-previews',
)]
class DeletePreviewsCommand extends Command
{
    public function __construct(readonly public Path $path)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output);

        $finder = new Finder();
        $filesystem = new Filesystem();
        $finder->in($this->path->joinWithBase(FolderNames::TEMPLATE->value));
        $finder->files()
            ->path('preview')
            ->name('*.webp')
            ->name('*.png');

        foreach ($finder as $file) {
            $filesystem->remove($file);
        }

        $io->complete('DONE');

        return Command::SUCCESS;
    }
}
