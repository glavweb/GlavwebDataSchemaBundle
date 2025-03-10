<?php

namespace Glavweb\DataSchemaBundle\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class DataSchemaConfiguration implements ConfigurationInterface
{
    public const PROPERTIES_DEFAULT_VALUES = [
        'schema'                        => null,
        'class'                         => null,
        'description'                   => null,
        'discriminator'                 => null,
        'ignore_discriminator_mismatch' => false,
        'filter_null_values'            => true,
        'join'                          => 'none',
        'type'                          => null,
        'source'                        => null,
        'decode'                        => null,
        'hidden'                        => false,
        'conditions'                    => [],
        'orderBy'                       => [],
        'roles'                         => [],
        'hasSubclasses'                 => false,
        'discriminatorColumnName'       => null,
        'discriminatorMap'              => [],
        'tableName'                     => null
    ];

    public const CONDITIONS_DEFAULT_VALUES = [
        'enabled'                       => true,
        'name'                          => null,
        'condition'                     => null
    ];

    public const SOURCE_SELF_TOKEN = '$';

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
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('schema');

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
                ->append($this->addConditionsNode())
                ->append($this->addPropertiesNode($this->nestingDepth))
            ->end()
        ;


        return $treeBuilder;
    }

    public function addPropertiesNode($depth)
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('properties');

        if ($depth === 0) {
            return $rootNode;
        }

        $rootNode
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('schema')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['schema'])
                    ->end()
                    ->scalarNode('class')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['class'])
                    ->end()
                    ->scalarNode('description')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['description'])
                    ->end()
                    ->scalarNode('discriminator')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['discriminator'])
                    ->end()
                    ->booleanNode('ignore_discriminator_mismatch')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['ignore_discriminator_mismatch'])
                    ->end()
                    ->booleanNode('filter_null_values')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['filter_null_values'])
                    ->end()
                    ->enumNode('join')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['join'])
                        ->values(['none', 'left', 'inner'])
                    ->end()
                    ->scalarNode('type')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['type'])
                    ->end()
                    ->scalarNode('source')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['source'])
                    ->end()
                    ->scalarNode('decode')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['decode'])
                    ->end()
                    ->scalarNode('hidden')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['hidden'])
                    ->end()
                    ->append($this->addConditionsNode())
                    ->arrayNode('orderBy')
                        ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['orderBy'])
                        ->variablePrototype()
                        ->end()
                        ->validate()
                            ->always(static function ($value) {
                                if (is_array($value)) {
                                    foreach ($value as $k => $v) {
                                        if (!in_array(strtolower($v), ['asc', 'desc'])) {
                                            throw new \InvalidArgumentException("The value \"{$v}\" for key \"$k\" is not a valid order by.");
                                        }
                                    }

                                    return $value;
                                } else {
                                    return null;
                                }
                            })
                        ->end()
                    ->end()
                ->end()
                ->append($this->addPropertiesNode(--$depth))
            ->end()
        ;

        return $rootNode;
    }

    public function addConditionsNode()
    {
        $treeBuilder = new TreeBuilder();

        return $treeBuilder->root('conditions')
            ->defaultValue(self::PROPERTIES_DEFAULT_VALUES['conditions'])
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->canBeDisabled()
                ->beforeNormalization()
                    ->ifString()
                    ->then(function ($v) { return ['condition' => $v]; })
                ->end()
                ->children()
                    ->scalarNode('name')->end()
                    ->scalarNode('condition')->isRequired()->end()
                ->end()
            ->end();
    }
}