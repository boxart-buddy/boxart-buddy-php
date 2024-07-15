<?php

namespace App\PostProcess\Option;

use App\PostProcess\CounterPostProcess;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

readonly class CounterPostProcessOptions implements ConfigurationInterface
{
    public const OFFSET_X = 'offset_x';
    public const OFFSET_Y = 'offset_y';
    public const POSITION = 'position';
    public const OPACITY = 'opacity';
    public const TEXT_COLOR = 'color';
    public const TEXT_FONT_FAMILY = 'font_family';
    public const TEXT_FONT_VARIANT = 'font_variant';
    public const SCALE = 'scale';
    public const BACKGROUND = 'background';
    public const BACKGROUND_OPACITY = 'background_opacity';
    public const VARIANT = 'variant';

    public const POSITION_VALUES = ['absolute-bottom-right', 'bottom-right', 'absolute-bottom-left', 'bottom-left', 'absolute-bottom', 'bottom', 'absolute-top', 'top', 'top-left', 'top-right', 'left', 'right'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(CounterPostProcess::NAME);

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode(self::BACKGROUND)
                    ->info('Should the counter have a background or not')
                    ->defaultFalse()
                ->end()
                ->floatNode(self::SCALE)
                    ->defaultValue(0.7)
                    ->info('Rescale the size of the counter by this factor')
                    ->min(0)
                    ->max(1)
                ->end()
                ->integerNode(self::OFFSET_Y)
                    ->info('The number of pixels to offset on the Y axis')
                ->end()
                ->integerNode(self::OFFSET_X)
                    ->info('The number of pixels to offset on the X axis')
                ->end()
                ->scalarNode(self::TEXT_COLOR)
                    ->defaultValue('white')
                ->end()
                ->scalarNode(self::TEXT_FONT_VARIANT)
                    ->defaultValue('bold')
                    ->info('Text Font Variant')
                ->end()
                ->scalarNode(self::TEXT_FONT_FAMILY)
                    ->defaultValue('roboto')
                    ->info('Text Font Family')
                ->end()
                ->integerNode(self::BACKGROUND_OPACITY)
                    ->info('The opacity of the background (if the counter is also transparent then these will stack)')
                    ->defaultValue(100)
                    ->max(100)
                    ->min(0)
                ->end()
                ->integerNode(self::OPACITY)
                    ->info('The opacity of the counter')
                    ->defaultValue(100)
                    ->max(100)
                    ->min(0)
                ->end()
                ->enumNode(self::VARIANT)
                    ->values(['simple', 'circular'])
                    ->defaultValue('simple')
                    ->info('Variant of the counter')
                ->end()
                ->enumNode(self::POSITION)
                    ->values(self::POSITION_VALUES)
                    ->defaultValue('bottom-right')
                    ->info('Position of the counter')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
