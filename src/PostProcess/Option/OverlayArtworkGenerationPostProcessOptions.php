<?php

namespace App\PostProcess\Option;

use App\PostProcess\OverlayArtworkGenerationPostProcess;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class OverlayArtworkGenerationPostProcessOptions implements ConfigurationInterface
{
    public const ARTWORK_PACKAGE = 'artwork_package';
    public const ARTWORK_FILE = 'artwork_file';
    public const FOLDER_PACKAGE = 'folder_package';
    public const FOLDER_FILE = 'folder_file';
    public const TOKEN = 'token';
    public const LAYER = 'layer';
    public const OFFSET_ORIGINAL_X = 'offset_original_x';
    public const OFFSET_ORIGINAL_Y = 'offset_original_y';
    public const OFFSET_GENERATED_X = 'offset_generated_x';
    public const OFFSET_GENERATED_Y = 'offset_generated_y';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(OverlayArtworkGenerationPostProcess::NAME);

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode(self::ARTWORK_PACKAGE)
                    ->info('The template-folder for the artwork.xml')
                ->end()
                ->scalarNode(self::ARTWORK_FILE)
                    ->info('The artwork.xml for the artwork')
                ->end()
                ->scalarNode(self::FOLDER_PACKAGE)
                    ->info('The template-folder for the artwork.xml')
                ->end()
                ->scalarNode(self::FOLDER_FILE)
                    ->info('The artwork.xml for the folder')
                ->end()
                ->scalarNode(self::TOKEN)
                    ->info('A token string to be used to translate artwork tokens')
                    ->defaultValue([])
                ->end()
                ->enumNode(self::LAYER)
                    ->values(['top', 'bottom'])
                    ->defaultValue('top')
                    ->info('Layer the option on top or underneath')
                ->end()
                ->integerNode(self::OFFSET_ORIGINAL_X)
                    ->defaultValue(0)
                    ->info('Offsets the original image X value')
                ->end()
                ->integerNode(self::OFFSET_ORIGINAL_Y)
                    ->defaultValue(0)
                    ->info('Offsets the original image Y value')
                ->end()
                ->integerNode(self::OFFSET_GENERATED_X)
                    ->defaultValue(0)
                    ->info('Offsets the generated image X value')
                ->end()
                ->integerNode(self::OFFSET_GENERATED_Y)
                    ->defaultValue(0)
                    ->info('Offsets the generated image Y value')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
