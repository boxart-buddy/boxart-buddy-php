<?php

namespace App\PostProcess\Option;

use App\PostProcess\BackgroundImagePostProcess;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class TextPostProcessOptions implements ConfigurationInterface
{
    public const POSITION = 'position';
    public const POSITIONS = ['center', 'center-bottom', 'bottom-left', 'bottom-right', 'center-top', 'top-left', 'top-right', 'bottom', 'top', 'left', 'right'];
    public const TEXT_COLOR = 'color';
    public const TEXT_COLOR_ALT = 'color_alt';
    public const USE_TEXT_COLOR_ALT = 'use_color_alt';
    public const TEXT_SIZE = 'size';
    public const TEXT_SIZES = ['xxxs', 'xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl'];
    public const TEXT_BG_COLOR = 'background_color';
    public const TEXT_BG_STYLE = 'background_style';
    public const TEXT_COLOR_INVERT = 'color_invert';
    public const TEXT_BG_STYLES = ['square', 'pill'];
    public const TEXT_BG_OPACITY = 'text_bg_opacity';
    public const TEXT_FONT_FAMILY = 'font_family';
    public const TEXT_FONT_VARIANT = 'font_variant';
    public const TRIM_BRACKETS = 'trim_brackets';
    public const TEXT_SHADOW = 'shadow';
    public const TEXT_X = 'text_x';
    public const TEXT_Y = 'text_y';
    public const TEXT_BG_WIDTH_FORCED = 'text_bg_width_forced';
    public const TEXT_BG_HEIGHT_FORCED = 'text_bg_height_forced';
    public const TEXT_TRUNCATE_NEG_WIDTH = 'text_truncate_neg_width';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(BackgroundImagePostProcess::NAME);

        $root = $treeBuilder->getRootNode();
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \RuntimeException();
        }

        $root
            ->children()
                ->integerNode(self::TEXT_BG_OPACITY)
                    ->defaultValue(0)->min(0)->max(100)
                    ->info('Text Background Opacity')
                ->end()
                ->scalarNode(self::TEXT_COLOR)
                    ->defaultValue('black')
                    ->info('Text Color')
                ->end()
                ->scalarNode(self::TEXT_COLOR_ALT)
                    ->defaultValue('silver')
                    ->info('Text Color Alt')
                ->end()
                ->booleanNode(self::USE_TEXT_COLOR_ALT)
                    ->defaultFalse()
                    ->info('If set to true will use the alt value for text color if it is set')
                ->end()
                ->booleanNode(self::TEXT_COLOR_INVERT)
                    ->defaultFalse()
                    ->info('If set to true will invert background and text color')
                ->end()
                ->scalarNode(self::TEXT_BG_COLOR)
                    ->defaultValue('white')
                    ->info('Text Background Color')
                ->end()
                ->scalarNode(self::TEXT_FONT_VARIANT)
                    ->defaultValue('bold')
                    ->info('Text Font Variant')
                ->end()
                ->scalarNode(self::TEXT_FONT_FAMILY)
                    ->defaultValue('roboto')
                    ->info('Text Font Family')
                ->end()
                ->enumNode(self::POSITION)
                    ->values(self::POSITIONS)
                    ->defaultValue('bottom')
                    ->info('The position of the text')
                ->end()
                ->enumNode(self::TEXT_BG_STYLE)
                    ->values(self::TEXT_BG_STYLES)
                    ->defaultValue('pill')
                    ->info('Background style')
                ->end()
                ->enumNode(self::TEXT_SIZE)
                    ->values(self::TEXT_SIZES)
                    ->defaultValue('m')
                    ->info('The size of the text')
                ->end()
                ->booleanNode(self::TEXT_SHADOW)
                    ->defaultFalse()
                    ->info('Adds a text shadow')
                ->end()
                ->booleanNode(self::TRIM_BRACKETS)
                    ->defaultTrue()
                    ->info('Removes content in () and [] brackets')
                ->end()
                ->integerNode(self::TEXT_X)
                    ->defaultValue(0)
                    ->info('Adjusts the x position of text')
                ->end()
                ->integerNode(self::TEXT_Y)
                    ->defaultValue(0)
                    ->info('Adjusts the y position of text')
                ->end()
                ->integerNode(self::TEXT_BG_WIDTH_FORCED)
                    ->defaultValue(0)
                    ->info('Forces the width of the background')
                ->end()
                ->integerNode(self::TEXT_BG_HEIGHT_FORCED)
                    ->defaultValue(0)
                    ->info('Forces the height of the background')
                ->end()
                ->integerNode(self::TEXT_TRUNCATE_NEG_WIDTH)
                    ->defaultValue(50)
                    ->info('The value from the end of the bg canvas at which text is truncated')
                ->end()

            ->end();

        return $treeBuilder;
    }
}
