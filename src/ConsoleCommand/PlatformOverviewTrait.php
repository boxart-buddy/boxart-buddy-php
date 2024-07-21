<?php

namespace App\ConsoleCommand;

use App\Config\InvalidConfigException;
use App\Config\Validator\ConfigValidator;
use App\Util\Console\BlockSectionHelper;

trait PlatformOverviewTrait
{
    protected function printPlatformOverview(BlockSectionHelper $io, ConfigValidator $configValidator): void
    {
        $io->section('platform-overview');

        try {
            $report = $configValidator->getPlatformReport();
        } catch (InvalidConfigException $e) {
            $io->failure($e->getMessage(), true);
            exit;
        }

        $tableHeader = ['Folder', 'Platform', 'File Count'];
        $tableBody = [];

        $total = 0;
        foreach ($report as $folder => $data) {
            $tableBody[] = [$folder, $data['platform'], $data['count']];
            $total = $total + $data['count'];
        }

        $tableBody[] = ['TOTAL', 'TOTAL', $total];

        $io->style()->table(
            $tableHeader,
            $tableBody
        );
    }
}
