<?php

namespace App\ConsoleCommand;

use App\Config\ConfigIO;
use App\Config\Processor\ApplicationConfigurationProcessor;
use App\Config\Reader\ConfigReader;
use App\FolderNames;
use App\Generator\ConfigFolderGenerator;
use App\Importer\FakeRomImporter;
use App\Lock\LockIO;
use App\Portmaster\PortmasterDataImporter;
use App\Util\Console\BlockSectionHelper;
use App\Util\Path;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'bootstrap',
    description: 'Bootstraps the application, creating config files etc.',
)]
class BootstrapCommand extends Command
{
    public function __construct(
        readonly private Path $path,
        readonly private PortmasterDataImporter $portmasterDataImporter,
        readonly private FakeRomImporter $fakeRomImporter,
        readonly private LoggerInterface $logger,
        readonly private ConfigFolderGenerator $configFolderGenerator,
        readonly private ConfigReader $configReader,
        readonly private LockIO $lockIO,
        readonly private ConfigIO $configIO
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'If set will overwrite any user configurations')
            ->addOption('stage-1', null, InputOption::VALUE_NONE, 'If set will quit after dumping basic config')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutput) {
            throw new \RuntimeException();
        }

        $io = new BlockSectionHelper($input, $output, $this->logger);
        $io->heading();

        $io->section('configs');
        $io->wait('Creating config files and folders');

        $overwrite = $input->getOption('overwrite');
        if ($overwrite) {
            $io->wait('Creating config files and folders (using overwrite mode)', true);
        }

        // config bootstrap
        $this->createNewFileFromDist(
            ApplicationConfigurationProcessor::CONFIG_FILENAME,
            'config/config.yml.dist',
            $overwrite
        );

        // config folder roms
        $this->createNewFileFromDist(
            ApplicationConfigurationProcessor::CONFIG_FOLDER_ROMS,
            'config/'.ApplicationConfigurationProcessor::CONFIG_FOLDER_ROMS.'.dist',
            $overwrite
        );

        // rom translations
        $this->createNewFileFromDist(
            ApplicationConfigurationProcessor::CONFIG_ROM_TRANSLATIONS,
            'config/'.ApplicationConfigurationProcessor::CONFIG_ROM_TRANSLATIONS.'.dist',
            $overwrite
        );

        // config portmaster
        $this->createNewFileFromDist(
            ApplicationConfigurationProcessor::CONFIG_PORTMASTER_FILENAME,
            'config/'.ApplicationConfigurationProcessor::CONFIG_PORTMASTER_FILENAME.'.dist',
            $overwrite
        );

        // config folder roms
        $this->createNewFileFromDist(
            ApplicationConfigurationProcessor::CONFIG_FOLDER_FILENAME,
            'config/'.ApplicationConfigurationProcessor::CONFIG_FOLDER_FILENAME.'.dist',
            $overwrite
        );

        // name_extra
        $this->createNewFileFromDist(
            'name_extra.json',
            'name_extra.json',
            $overwrite
        );

        // make sure folders exist
        // maybe this is now redundant...
        $filesystem = new Filesystem();
        foreach (FolderNames::values() as $folder) {
            if ($filesystem->exists($this->path->joinWithBase($folder))) {
                continue;
            }
            $filesystem->mkdir($this->path->joinWithBase($folder));
        }

        $io->done('Creating config files and folders', true);

        if ($input->getOption('stage-1')) {
            return Command::SUCCESS;
        }

        $configuredRomFolder = $this->configIO->read(ApplicationConfigurationProcessor::CONFIG_FILENAME, 'rom_folder');
        if (!$configuredRomFolder) {
            $io->failure('Value of `rom_folder` in `user_config/config.yml` is missing and must be entered before proceeding');

            return Command::FAILURE;
        }

        $filesystem = new Filesystem();

        if (!$filesystem->exists($configuredRomFolder)) {
            $io->failure('Value of `rom_folder`: `%s` is an invalid path, the path must exist and be a folder that contains folders of roms', $configuredRomFolder);

            return Command::FAILURE;
        }

        $configFolderPath = $this->path->joinWithBase(FolderNames::USER_CONFIG->value, ApplicationConfigurationProcessor::CONFIG_FOLDER_FILENAME);
        if (!$filesystem->exists($configFolderPath)) {
            $filesystem->dumpFile($configFolderPath, '');
        }
        $lockRomfolderHash = $this->lockIO->read(LockIO::KEY_ROMFOLDER_HASH);
        $romfolderHash = Path::hashForDirectoryContents($this->configReader->getConfig()->romFolder, true);

        if ($overwrite || ($lockRomfolderHash !== $romfolderHash) || !$filesystem->exists($configFolderPath)) {
            $io->section('config_folder');
            $io->wait('Generating config_folder.yml');

            $this->configFolderGenerator->generateConfigFolderFile();
            $this->lockIO->write(LockIO::KEY_ROMFOLDER_HASH, $romfolderHash);
            $io->done('Generating config_folder.yml', true);
        }

        $io->section('portmaster')->wait('Importing Portmaster data');
        $this->portmasterDataImporter->importPortmasterDataIfNotImportedSince(new \DateInterval('PT5M'));
        $io->done('Importing Portmaster data', true);

        $io->section('dummy-roms')->wait('Importing dummy roms');
        $this->fakeRomImporter->import();
        $io->done('Imported dummy roms', true);

        $io->section('end')->complete(
            'Bootstrap complete. Edit config.yml to set credentials and preferences'
        );

        return Command::SUCCESS;
    }

    private function createNewFileFromDist(string $filename, string $distFilename, bool $overwrite): void
    {
        $filesystem = new Filesystem();

        if (!$overwrite && $filesystem->exists($this->path->joinWithBase(FolderNames::USER_CONFIG->value, $filename))) {
            return;
        }

        $filesystem->copy(
            $this->path->joinWithBase('resources', $distFilename),
            $this->path->joinWithBase(FolderNames::USER_CONFIG->value, $filename),
            $overwrite
        );
    }
}
