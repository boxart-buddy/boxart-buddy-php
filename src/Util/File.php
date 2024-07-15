<?php

namespace App\Util;

use Symfony\Component\Process\Process;

readonly class File
{
    public static function addCommentAboveLine(string $absoluteFilePath, string $needle, string $comment): void
    {
        $command = [
            'perl',
            '-i',
            '-pe',
            sprintf('print "# %s\n" if /%s/', $comment, $needle),
            $absoluteFilePath,
        ];
        $process = new Process($command);
        $process->run();
    }
}
