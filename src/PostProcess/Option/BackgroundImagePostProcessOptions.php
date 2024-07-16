<?php

namespace App\PostProcess\Option;

use App\PostProcess\BackgroundImagePostProcess;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class BackgroundImagePostProcessOptions implements ConfigurationInterface
{
    public const BACKGROUND_DEFAULT = 'background_default';
    public const USE_BACKGROUND_DEFAULT = 'use_background_default';
    public const BACKGROUND = 'background';
    public const OVERLAY = 'overlay';
    public const OFFSET_ORIGINAL_X = 'offset_original_x';
    public const OFFSET_ORIGINAL_Y = 'offset_original_y';
    public const EXCLUDE = 'exclude';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(BackgroundImagePostProcess::NAME);

        $root = $treeBuilder->getRootNode();
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \RuntimeException();
        }

        $root
            ->children()
                ->scalarNode(self::OVERLAY)
                    ->info('The overlay image file: resources/background/{image.png}')
                ->end()
                ->scalarNode(self::BACKGROUND)
                    ->info('The background image file: resources/background/{image.png}')
                ->end()
                ->scalarNode(self::BACKGROUND_DEFAULT)
                    ->info('The default background image file, this is usually the same as that from the theme file')
                ->end()
                ->booleanNode(self::USE_BACKGROUND_DEFAULT)
                    ->defaultFalse()
                    ->info('Should the default background be used? Default is false')
                ->end()
                ->integerNode(self::OFFSET_ORIGINAL_X)
                    ->defaultValue(0)
                    ->info('Offsets the original image X value')
                ->end()
                ->integerNode(self::OFFSET_ORIGINAL_Y)
                    ->defaultValue(0)
                    ->info('Offsets the original image Y value')
                ->end()
                ->enumNode(self::EXCLUDE)
                    ->values(['artwork', 'folder'])
                    ->defaultValue('')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
