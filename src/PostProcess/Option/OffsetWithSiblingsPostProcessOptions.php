<?php

namespace App\PostProcess\Option;

use App\PostProcess\OffsetWithSiblingsPostProcess;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class OffsetWithSiblingsPostProcessOptions implements ConfigurationInterface
{
    public const OFFSET_Y = 'offset_y';
    public const OFFSET_Y_MODE = 'offset_y_mode';
    public const OFFSET_X = 'offset_x';
    public const OFFSET_X_MODE = 'offset_x_mode';
    public const SIBLING_COUNT = 'sibling_count';
    public const SCALE = 'scale';
    public const EFFECT = 'effect';
    public const OPACITY = 'opacity';
    public const LOOP = 'loop';
    public const CIRCLE = 'circle';
    public const RENDER = 'render';
    public const CIRCLE_RADIUS = 'circle_radius';

    // regular = offset*sibling index
    // scale = regular adjusted for scale
    // fixed = offset
    // adjust = keep pixel at offset co-ordinates at same position relative to previous sibling
    public const OFFSET_MODES = ['regular', 'scale', 'fixed', 'adjust'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(OffsetWithSiblingsPostProcess::NAME);

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->enumNode(self::RENDER)
                    ->defaultValue('medium')
                    ->values(['both', 'ahead', 'behind'])
                    ->defaultValue('both')
                    ->info('Which siblings to render, by default siblings behind and ahead are rendered')
                ->end()
                ->integerNode(self::CIRCLE_RADIUS)
                    ->defaultValue(320)
                    ->min(10)
                    ->info('The radius of the circle. Only used if `circle` is set')
                ->end()
                ->enumNode(self::CIRCLE)
                    ->values(['half-circle-left', 'half-circle-right', 'half-circle-top', 'half-circle-bottom'])
                    ->defaultNull()
                    ->info('The placement of the circle releative to the first element')
                ->end()
                ->booleanNode(self::LOOP)
                    ->defaultTrue()
                    ->info('If set to true then first and last sibling will be amended to show a loop (rather than blanks)')
                ->end()
                ->enumNode(self::OFFSET_Y_MODE)
                    ->values(self::OFFSET_MODES)
                    ->defaultValue('regular')
                    ->info('How the offset Y should be applied')
                ->end()
                ->enumNode(self::OFFSET_X_MODE)
                    ->values(self::OFFSET_MODES)
                    ->defaultValue('regular')
                    ->info('How the offset X should be applied')
                ->end()
                ->floatNode(self::OPACITY)
                    ->info('Opacity parameter will make siblings transparent using a logarithmic scale. A float between 0-1 (e.g 0.8), the last sibling will be this percent transparent')
                    ->min(0)
                    ->max(100)
                    ->defaultNull()
                ->end()
                ->floatNode(self::SCALE)
                    ->info('Scale parameter will resize siblings using a logarithmic scale. A float between 0-1 (e.g 0.8), the last sibling will be scaled to this size')
                    ->min(0)
                    ->max(100)
                    ->defaultNull()
                ->end()
                ->enumNode(self::EFFECT)
                    ->values(['greyscale', 'blur', 'pixelate', null])
                    ->defaultNull()
                    ->info('Effect parameter will apply a graphical effect to siblings, can use more than one')
                ->end()
                ->integerNode(self::SIBLING_COUNT)
                    ->info('The number of siblings to offset in each direction (e.g setting 2 will yield 4 siblings)')
                    ->defaultValue(5)
                ->end()
                ->integerNode(self::OFFSET_Y)
                    ->defaultValue(0)
                    ->info('The number of pixels to offset on the Y axis')
                ->end()
                ->integerNode(self::OFFSET_X)
                    ->defaultValue(0)
                    ->info('The number of pixels to offset on the X axis')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
