<?php

namespace App\Command;

use App\Portmaster\PortmasterDataImporter;
use App\Util\Console\BlockSectionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'portmaster-read-metadata',
    description: 'Debug command, dumps the name of portmaster roms for use in config files',
)]
class PortmasterReadMetadataCommand extends Command
{
    public function __construct(readonly PortmasterDataImporter $portmasterDataImporter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('field', InputArgument::REQUIRED, 'One of `title`, `zipName`, `name`, `description`, `genre`, `script`')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $metadata = $this->portmasterDataImporter->getMetaData(true);
        $metadata = array_map(function ($data) {
            unset($data['description']);

            return $data;
        }, $metadata);

        $io = new BlockSectionHelper($input, $output);

        if (0 === count($metadata)) {
            $io->failure('Portmaster metadata could not be loaded');
        }

        $field = $input->getArgument('field');

        if ($field) {
            $metadata = array_map(function ($data) use ($field) {
                if (!array_key_exists($field, $data)) {
                    throw new \InvalidArgumentException(sprintf('Cannot read unknown field: %s', $field));
                }

                return [$field => $data[$field]];
            }, $metadata);
        }

        $headers = array_keys(reset($metadata));

        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($metadata)
        ;
        $table->render();

        return Command::SUCCESS;
    }
}
