<?php

namespace App\PostProcess;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

trait ProcessOptionsTrait
{
    public function processOptions(array $options, string $optionClass): array
    {
        $processor = new Processor();
        $config = new $optionClass();

        if (!$config instanceof ConfigurationInterface) {
            throw new \RuntimeException();
        }

        return $processor->processConfiguration($config, [$options]);
    }
}
