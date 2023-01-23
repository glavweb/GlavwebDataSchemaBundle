<?php

namespace Glavweb\DataSchemaBundle\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class DataSchemaConfiguration implements ConfigurationInterface
{
    /**
     * @var int
     */
    private $nestingDepth;

    /**
     * DataSchemaConfiguration constructor.
     *
     * @param int $nestingDepth
     */
    public function __construct(int $nestingDepth = 0)
    {
        $this->nestingDepth = $nestingDepth;
    }

    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('schema');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('schema')
                ->end()
                ->enumNode('db_driver')
                    ->isRequired()
                    ->values(['orm'])
                ->end()
                ->scalarNode('class')
                    ->isRequired()
                ->end()
                ->arrayNode('roles')
                    ->beforeNormalization()
                        ->castToArray()
                    ->end()
                ->end()
                ->booleanNode('filter_null_values')
                    ->defaultTrue()
                ->end()
                ->arrayNode('query')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('selects')
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->append($this->addPropertiesNode($this->nestingDepth))
            ->end()
        ;


        return $treeBuilder;
    }

    public function addPropertiesNode($depth)
    {
        $treeBuilder = new TreeBuilder('properties');

        $rootNode = $treeBuilder->getRootNode();

        if ($depth === 0) {
            return $rootNode;
        }

        $rootNode
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('schema')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('class')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('description')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('discriminator')
                        ->defaultNull()
                    ->end()
                    ->booleanNode('ignore_discriminator_mismatch')
                        ->defaultFalse()
                    ->end()
                    ->booleanNode('filter_null_values')
                        ->defaultTrue()
                    ->end()
                    ->enumNode('join')
                        ->defaultValue('none')
                        ->values(['none', 'left', 'inner'])
                    ->end()
                    ->scalarNode('type')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('source')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('decode')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('hidden')
                        ->defaultFalse()
                    ->end()
                    ->arrayNode('conditions')
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
                ->append($this->addPropertiesNode(--$depth))
            ->end()
        ;

        return $rootNode;
    }
}