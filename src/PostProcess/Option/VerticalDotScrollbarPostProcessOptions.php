<?php

namespace App\PostProcess\Option;

use App\PostProcess\VerticalDotScrollbarPostProcess;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

readonly class VerticalDotScrollbarPostProcessOptions implements ConfigurationInterface
{
    public const POSITION = 'position';
    public const OPACITY = 'opacity';
    public const DOTCOLOR = 'dotcolor';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(VerticalDotScrollbarPostProcess::NAME);

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode(self::DOTCOLOR)
                    ->defaultValue('white')
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
            ->end();

        return $treeBuilder;
    }
}
