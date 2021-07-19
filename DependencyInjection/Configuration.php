<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 *
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 *
 * @package Glavweb\DataSchemaBundle
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('glavweb_data_schema');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_hydrator_mode')->cannotBeEmpty()->end()
                ->arrayNode('data_schema')
                    ->children()
                        ->scalarNode('dir')->end()
                        ->integerNode('max_nesting_depth')->defaultValue(10)->min(1)->end()
                    ->end()
                ->end()
                ->arrayNode('scope')
                    ->children()
                        ->scalarNode('dir')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
