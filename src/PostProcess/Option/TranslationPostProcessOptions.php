<?php

namespace App\PostProcess\Option;

use App\PostProcess\TranslationPostProcess;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class TranslationPostProcessOptions implements ConfigurationInterface
{
    public const POSITION = 'position';
    public const POSITIONS = ['center', 'center-bottom', 'bottom-left', 'bottom-right', 'center-top', 'top-left', 'top-right', 'bottom', 'top', 'left', 'right'];
    public const MAPPING = 'mapping';
    public const TEXT_COLOR = 'color';
    public const TEXT_BG_OPACITY = 'text_bg_opacity';
    public const TEXT_FONT_FAMILY = 'font_family';
    public const TEXT_FONT_VARIANT = 'font_variant';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(TranslationPostProcess::NAME);

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode(self::TEXT_FONT_VARIANT)
                    ->defaultValue('bold')
                    ->info('Text Font Variant')
                ->end()
                ->scalarNode(self::TEXT_FONT_FAMILY)
                    ->defaultValue('roboto')
                    ->info('Text Font Family')
                ->end()
                ->integerNode(self::TEXT_BG_OPACITY)
                    ->defaultValue(90)->min(0)->max(100)
                    ->info('Text Background Opacity')
                ->end()
                ->scalarNode(self::TEXT_COLOR)
                    ->defaultValue('black')
                    ->info('Text Color')
                ->end()
                ->scalarNode(self::MAPPING)
                    ->defaultValue('rom_translations.yml')
                    ->info('Filename containing mapping of romname to text')
                ->end()
                ->enumNode(self::POSITION)
                    ->values(self::POSITIONS)
                    ->defaultValue('bottom')
                    ->info('The position of the translation')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
