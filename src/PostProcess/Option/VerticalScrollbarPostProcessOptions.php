<?php

namespace App\PostProcess\Option;

use App\PostProcess\VerticalScrollbarPostProcess;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

readonly class VerticalScrollbarPostProcessOptions implements ConfigurationInterface
{
    public const POSITION = 'position';
    public const OPACITY = 'opacity';
    public const TRACK_HEIGHT = 'track_height';
    public const TRACK_WIDTH = 'track_width';
    public const THUMB_HEIGHT = 'thumb_height';
    public const TRACK_COLOR = 'track_color';
    public const THUMB_COLOR = 'thumb_color';
    public const TRACK_STYLE = 'track_style';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(VerticalScrollbarPostProcess::NAME);

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode(self::THUMB_COLOR)
                    ->defaultValue('8FB7C4')
                    ->info('The color of the scrollbar track')
                ->end()
                ->scalarNode(self::TRACK_COLOR)
                    ->defaultValue('6E8284')
                    ->info('The color of the scrollbar thumb')
                ->end()
                ->floatNode(self::OPACITY)
                    ->info('The opacity of the scrollbar')
                    ->min(0)
                    ->max(100)
                    ->defaultValue(100)
                ->end()
                ->enumNode(self::POSITION)
                    ->values(['left', 'right'])
                    ->defaultValue('left')
                    ->info('The horizontal placement of the bar')
                ->end()
                ->integerNode(self::THUMB_HEIGHT)
                    ->min(2)
                    ->defaultValue(28)
                    ->info('The height of the thumb')
                ->end()
                ->integerNode(self::TRACK_HEIGHT)
                    ->min(10)
                    ->max(480)
                    ->defaultValue(300)
                    ->info('The height of the track')
                ->end()
                ->integerNode(self::TRACK_WIDTH)
                    ->min(2)
                    ->max(320)
                    ->defaultValue(12)
                    ->info('The width of the track')
                ->end()
                ->enumNode(self::TRACK_STYLE)
                    ->values(['rounded', 'square'])
                    ->defaultValue('rounded')
                    ->info('Rounded or square bar')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
