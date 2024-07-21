<?php

namespace App\ConsoleCommand;

use App\Config\Reader\ConfigReader;
use App\Lock\LockIO;
use App\Util\Console\BlockSectionHelper;
use App\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

trait PreflightCheckTrait
{
    protected function runPreflightChecks(
        BlockSectionHelper $io,
        ConfigReader $configReader,
        LockIO $lockIO,
    ): void {
        $io->section('preflight-checks');

        $io->wait('Running preflight checks... (may be slow on network shares or large romsets, stand by)');

        $romFolder = $configReader->getConfig()->romFolder;
        $filesystem = new Filesystem();
        if (!$filesystem->exists($romFolder)) {
            $io->failure(sprintf('romFolder not found: %s', $romFolder));
        }

        $lockRomfolderHash = $lockIO->read(LockIO::KEY_ROMFOLDER_HASH);
        $romfolderHash = Path::hashForDirectoryContents($romFolder, true);

        if ($lockRomfolderHash !== $romfolderHash) {
            $message = "There has been a change to the romFolder contents since the last time `make bootrap` was run.\nPlease rerun `make bootstrap` and try again";
            $io->failure($message, true);

            exit(Command::FAILURE);
        }

        $this->checkSkyscraperInstalled($io);
        $this->checkPeasPresent($io, $this->configReader);
        $this->checkPackageConfigForEveryFolder($io, $this->configReader);

        $io->done('Preflight checks complete', true);
    }

    private function checkSkyscraperInstalled(BlockSectionHelper $io): void
    {
        // validate the skyscraper install
        if (!$this->checkPackageIsInstalled('Skyscraper')) {
            $message = 'Skyscraper is not installed, make sure Skyscraper is installed: https://github.com/Gemba/Skyscraper/';
            $io->failure($message, true);

            exit(Command::FAILURE);
        }
    }

    private function checkPackageConfigForEveryFolder(BlockSectionHelper $io, ConfigReader $configReader): void
    {
        foreach ($configReader->getConfig()->folders as $folderName => $platformName) {
            if (!array_key_exists($platformName, $this->configReader->getConfig()->package)) {
                $message = sprintf('Platform `%s` is not represented in the package_muos_config.yml mapping file, please add it', $platformName);
                $io->failure($message, true);

                exit(Command::FAILURE);
            }
        }
    }

    private function checkPeasPresent(BlockSectionHelper $io, ConfigReader $configReader): void
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists(Path::join($configReader->getConfig()->skyscraperConfigFolderPath, 'peas.json'))) {
            $message = 'Skyscraper configuration file `peas.json` not found, make sure you have the LATEST version of this Skyscraper fork installed: https://github.com/Gemba/Skyscraper/';
            $io->failure($message, true);

            exit(Command::FAILURE);
        }
    }

    private function checkPackageIsInstalled(string $package): bool
    {
        $cmd = sprintf('which %s | grep -o %s > /dev/null &&  echo 0 || echo 1', $package, $package);
        $process = Process::fromShellCommandline($cmd);

        $process->run();

        return '0' === trim($process->getOutput());
    }
}
