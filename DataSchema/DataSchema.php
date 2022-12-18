<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DataSchema;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Glavweb\DataSchemaBundle\DataSchema\Persister\PersisterFactory;
use Glavweb\DataSchemaBundle\DataSchema\Persister\PersisterInterface;
use Glavweb\DataSchemaBundle\DataTransformer\TransformEvent;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationException;
use Glavweb\DataSchemaBundle\Exception\DataTransformer\DataTransformerNotExists;
use Glavweb\DataSchemaBundle\Exception\Persister\InvalidQueryException;
use Glavweb\DataSchemaBundle\Hydrator\Doctrine\ObjectHydrator;
use Glavweb\DataSchemaBundle\Service\DataSchemaFilter;
use Glavweb\DataSchemaBundle\Service\DataSchemaService;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class DataSchema
 *
 * @author  Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
class DataSchema
{
    public const DEFAULTS = [
        'schema'                  => null,
        'class'                   => null,
        'description'             => null,
        'discriminator'           => null,
        'filter_null_values'      => true,
        'join'                    => 'none',
        'type'                    => null,
        'source'                  => null,
        'decode'                  => null,
        'hidden'                  => false,
        'conditions'              => [],
        'roles'                   => [],
        'hasSubclasses'           => false,
        'discriminatorColumnName' => null,
        'discriminatorMap'        => [],
        'tableName'               => null
    ];

    /**
     * @var DataSchemaFactory
     */
    private $dataSchemaFactory;

    /**
     * @var PersisterInterface
     */
    private $persister;

    /**
     * @var Placeholder
     */
    private $placeholder;

    /**
     * @var array
     */
    private $configuration;

    /**
     * @var array
     */
    private $scopeConfig;

    /**
     * @var int
     */
    private $nestingDepth;

    /**
     * @var string|null
     */
    private $defaultHydratorMode;

    /**
     * @var ObjectHydrator
     */
    private $objectHydrator;

    /**
     * @var DataSchemaService
     */
    private $dataSchemaService;

    /**
     * @var DataSchemaFilter
     */
    private $dataSchemaFilter;

    /**
     * DataSchema constructor.
     *
     * @param DataSchemaFactory $dataSchemaFactory
     * @param DataSchemaService $dataSchemaService
     * @param DataSchemaFilter  $dataSchemaFilter
     * @param PersisterFactory  $persisterFactory
     * @param Placeholder       $placeholder
     * @param ObjectHydrator    $objectHydrator
     * @param array             $configuration
     * @param array|null        $scopeConfig
     * @param int|null          $nestingDepth
     * @param string|null       $defaultHydratorMode
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    public function __construct(DataSchemaFactory $dataSchemaFactory,
                                DataSchemaService $dataSchemaService,
                                DataSchemaFilter $dataSchemaFilter,
                                PersisterFactory $persisterFactory,
                                Placeholder $placeholder,
                                ObjectHydrator $objectHydrator,
                                array $configuration,
                                array $scopeConfig = null,
                                int $nestingDepth = null,
                                string $defaultHydratorMode = null)
    {
        $this->dataSchemaFactory   = $dataSchemaFactory;
        $this->dataSchemaService   = $dataSchemaService;
        $this->dataSchemaFilter    = $dataSchemaFilter;
        $this->placeholder         = $placeholder;
        $this->objectHydrator      = $objectHydrator;
        $this->nestingDepth        = $nestingDepth;
        $this->defaultHydratorMode = $defaultHydratorMode;

        $this->persister   = $persisterFactory->createPersister($configuration['db_driver'], $this);
        $this->scopeConfig = $scopeConfig;

        $this->dataSchemaService->startStopwatch('filter');

        $configuration = $this->dataSchemaFilter->filter($configuration, $scopeConfig, $nestingDepth);

        $this->dataSchemaService->stopStopwatch('filter');
        $this->dataSchemaService->startStopwatch('prepareConfiguration');

        $this->configuration =
            $this->prepareConfiguration($configuration, $configuration['class'], $scopeConfig, $this->nestingDepth);
        $this->dataSchemaService->stopStopwatch('prepareConfiguration');

    }

    /**
     * @param string $propertyName
     * @return bool
     */
    public function hasProperty(string $propertyName): bool
    {
        return $this->getPropertyConfiguration($propertyName) !== null;
    }

    /**
     * @param string $propertyName
     * @return bool
     */
    public function hasPropertyInDb(string $propertyName): bool
    {
        $propertyConfiguration = $this->getPropertyConfiguration($propertyName);

        return $propertyConfiguration !== null && isset($propertyConfiguration['from_db'])
            && $propertyConfiguration['from_db'];
    }

    /**
     * @param string             $condition
     * @param string             $alias
     * @param UserInterface|null $user
     * @return string
     */
    public function conditionPlaceholder(string $condition, string $alias, UserInterface $user = null): string
    {
        return $this->placeholder->condition($condition, $alias, $user);
    }

    /**
     * @param array       $configuration
     * @param string|null $class
     * @param array|null  $scopeConfig
     * @param int         $nestingDepth
     * @return array
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    protected function prepareConfiguration(array $configuration,
                                            ?string $class,
                                            array $scopeConfig = null,
                                            int $nestingDepth = 0): array
    {
        $class = $class ?? $configuration['class'] ?? null;

        $configuration          = array_replace(self::DEFAULTS, $configuration);
        $configuration['class'] = $class;

        if (!$this->dataSchemaFilter->isGranted($configuration['roles'])) {
            return [];
        }

        // class
        $classMetadata        = $class ? $this->dataSchemaService->getClassMetadata($class) : null;
        $identifierFieldNames = [];
        $discriminatorMap     = null;

        if ($classMetadata instanceof ClassMetadata) {
            if ($classMetadata->subClasses) {
                $configuration['hasSubclasses']           = true;
                $configuration['discriminatorColumnName'] = $classMetadata->discriminatorColumn['name'];
                $configuration['discriminatorMap']        = $classMetadata->discriminatorMap;
                $discriminatorMap                         = $configuration['discriminatorMap'];
            }

            $configuration['tableName'] = $classMetadata->getTableName();
            $identifierFieldNames       = $classMetadata->getIdentifierFieldNames();
        }
        $configProperties = $configuration['properties'] ?? [];
        $properties       = [];

        foreach ($identifierFieldNames as $idName) {
            if (!array_key_exists($idName, $configProperties)) {
                $configProperties[$idName]            = array_merge(self::DEFAULTS, ['hidden' => true]);
                $configuration['properties'][$idName] = $configProperties[$idName];
            }
        }

        foreach ($configProperties as $propertyName => $propertyConfig) {
            $propertyScopeConfig = $scopeConfig[$propertyName] ?? null;
            $schema              = $propertyConfig['schema'] ?? null;
            $isNested            = $this->dataSchemaService->isNestedProperty($propertyConfig);

            if ($schema) {
                $propertyConfig = $this->getNestedDataSchemaConfiguration(
                    $schema,
                    $propertyConfig,
                    $nestingDepth - 1,
                    $propertyScopeConfig
                );
            }

            $discriminator = $propertyConfig['discriminator'] ?? null;
            $subClass      = $discriminatorMap && $discriminator ? $discriminatorMap[$discriminator] ?? null : null;

            $propertyOwnerClassMetadata =
                $subClass ? $this->dataSchemaService->getClassMetadata($subClass) : $classMetadata;

            // set default description
            if (empty($propertyConfig['description']) && $propertyOwnerClassMetadata instanceof ClassMetadata
                && $propertyOwnerClassMetadata->hasField($propertyName)) {

                $fieldMapping = $propertyOwnerClassMetadata->getFieldMapping($propertyName);
                $description  = $fieldMapping['options']['comment'] ?? null;

                $propertyConfig['description'] = $description;
            }

            if ($isNested) {
                $propertyClass = $propertyConfig['class'] ?? null;

                if (!$propertyClass && $propertyOwnerClassMetadata instanceof ClassMetadata
                    && $propertyOwnerClassMetadata->hasAssociation($propertyName)) {

                    $propertyClass = $propertyOwnerClassMetadata->getAssociationTargetClass($propertyName);
                }

                $propertyConfig = $this->prepareConfiguration(
                    $propertyConfig,
                    $propertyClass,
                    $propertyScopeConfig,
                    $nestingDepth - 1
                );

                if ($propertyConfig && $propertyOwnerClassMetadata instanceof ClassMetadata
                    && $propertyOwnerClassMetadata->hasAssociation($propertyName)) {

                    $isCollection = $propertyOwnerClassMetadata->isCollectionValuedAssociation($propertyName);

                    $propertyConfig['type'] = $isCollection ? 'collection' : 'entity';
                }

            } else if ($propertyOwnerClassMetadata instanceof ClassMetadata) {
                $propertyConfig['type'] =
                    $propertyConfig['type'] ?? $propertyOwnerClassMetadata->getTypeOfField($propertyName);

                $propertyConfig['from_db'] = $propertyOwnerClassMetadata->hasField($propertyName);

                $propertyConfig['field_db_name'] =
                    $propertyConfig['from_db'] ? $propertyOwnerClassMetadata->getColumnName($propertyName) : null;
            }

            $properties[$propertyName] = $propertyConfig;
        }

        $configuration['properties'] = $properties;

        return $configuration;
    }

    /**
     * @param mixed          $value
     * @param string         $decodeString
     * @param TransformEvent $transformEvent
     * @return mixed
     * @throws DataTransformerNotExists
     */
    protected function decode($value, string $decodeString, TransformEvent $transformEvent)
    {
        $dataTransformerNames = $this->dataSchemaService->parseDecodeString($decodeString);

        foreach ($dataTransformerNames as $dataTransformerName) {
            $transformer = $this->dataSchemaService->getDataTransformer($dataTransformerName);
            $value       = $transformer->transform($value, $transformEvent);
        }

        return $value;
    }

    /**
     * @param array      $data
     * @param array      $config
     * @param array|null $scopeConfig
     * @return array
     * @throws InvalidConfigurationException
     * @throws MappingException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws InvalidQueryException
     */
    private function fetchMissingPropertiesRecursive(array $data, array $config, array $scopeConfig = null): array
    {
        $id            = $data['id'] ?? null;
        $class         = $this->getDataClassName($config, $data);
        $discriminator = $config['hasSubclasses'] ? $this->getDiscriminatorValue($config, $data) : null;
        $metadata      = $this->dataSchemaService->getClassMetadata($class);

        $result = $data + [];
        $fields = [];

        foreach ($config['properties'] as $propertyName => $propertyConfig) {
            $propertyScopeConfig   = $scopeConfig[$propertyName] ?? [];
            $propertyDiscriminator = $propertyConfig['discriminator'] ?? null;
            $isNested              = $this->dataSchemaService->isNestedProperty($propertyConfig);
            $isFromDb              = $propertyConfig['from_db'] ?? false;

            $value  = null;
            $source = $propertyConfig['source'] ?? null;

            if ($discriminator && $propertyDiscriminator && $discriminator !== $propertyDiscriminator) {
                continue;
            }

            if ($source) {
                $querySelects = $this->getQuerySelects($config);
                $select = $querySelects[$source] ?? null;

                if ($select) {
                    $data[$source] = $this->persister->getSelectQueryResult($class, $select, $id);
                    $value = $data[$source];
                }

            } elseif (array_key_exists($propertyName, $data)) {
                $value = $data[$propertyName];

                if ($isNested && is_array($value)) {
                    if ($this->isIterablePropertyType($propertyConfig['type'])) {
                        $value = array_map(
                            function ($itemData) use ($propertyConfig, $propertyScopeConfig) {
                                return $this->fetchMissingPropertiesRecursive(
                                    $itemData,
                                    $propertyConfig,
                                    $propertyScopeConfig
                                );
                            },
                            $value
                        );
                    } else {
                        $value = $this->fetchMissingPropertiesRecursive($value, $propertyConfig, $propertyScopeConfig);
                    }
                }
            } else if ($isNested) {
                if (!$id || !$metadata->hasAssociation($propertyName)) {
                    continue;
                }

                $value = $this->fetchMissingAssociationRecursive(
                    $metadata,
                    $propertyName,
                    $propertyConfig,
                    $propertyScopeConfig,
                    $id
                );
            } else if ($isFromDb && $metadata->hasField($propertyName)) {
                $fields[] = $propertyName;
                continue;
            } else {
                continue;
            }

            $result[$propertyName] = $value;
        }

        if ($fields && $id) {
            $fieldsData = $this->persister->getPropertiesData($class, $fields, $id);

            foreach ($fieldsData as $fieldName => $fieldValue) {
                $result[$fieldName] = $fieldValue;
            }
        }

        return $result;
    }

    /**
     * @param array       $data
     * @param array       $config
     * @param array|null  $scopeConfig
     * @param string|null $parentClassName
     * @param string|null $parentPropertyName
     * @return array
     * @throws DataTransformerNotExists
     */
    private function modifyPropertiesRecursive(array $data,
                                               array $config,
                                               array $scopeConfig = null,
                                               string $parentClassName = null,
                                               string $parentPropertyName = null): array
    {
        $class         = $this->getDataClassName($config, $data);
        $discriminator = $config['hasSubclasses'] ? $this->getDiscriminatorValue($config, $data) : null;

        $result = [];

        foreach ($config['properties'] as $propertyName => $propertyConfig) {
            $value                 = null;
            $propertyScopeConfig   = $scopeConfig[$propertyName] ?? [];
            $propertyDiscriminator = $propertyConfig['discriminator'] ?? null;
            $isHidden              = $propertyConfig['hidden'] ?? false;
            $source                = $propertyConfig['source'] ?? null;

            if ($discriminator && $propertyDiscriminator && $discriminator !== $propertyDiscriminator) {
                continue;
            }

            if ($source) {
                if (!array_key_exists($source, $data)) {
                    throw new \RuntimeException("Property \"$source\" must be defined.");
                }
                $value = $data[$source];

            } elseif (array_key_exists($propertyName, $data)) {
                $value = $data[$propertyName];

            }

            if (is_array($value)) {
                if (!array_key_exists('type', $propertyConfig)) {
                    throw new \RuntimeException('Property "type" must be defined.');
                }

                if ($propertyConfig['type'] === 'entity') {
                    if (!$this->isOnlyNullInArray($value)) {
                        $value = $this->modifyPropertiesRecursive(
                            $value,
                            $propertyConfig,
                            $propertyScopeConfig,
                            $class,
                            $propertyName
                        );

                    } else if (!$config['filter_null_values']) {
                        $value = null;
                    }

                } elseif ($propertyConfig['type'] === 'collection') {
                    $value = array_map(
                        function ($itemData) use (
                            $propertyConfig,
                            $propertyScopeConfig,
                            $class,
                            $propertyName
                        ) {
                            return $this->modifyPropertiesRecursive(
                                $itemData,
                                $propertyConfig,
                                $propertyScopeConfig,
                                $class,
                                $propertyName
                            );
                        },
                        $value
                    );

                }
            }

            if ($propertyConfig['decode']) {
                $transformEvent = new TransformEvent(
                    $class,
                    $propertyName,
                    $propertyConfig,
                    $parentClassName,
                    $parentPropertyName,
                    $data,
                    $this->objectHydrator,
                    $this->dataSchemaFactory
                );

                $value = $this->decode($value, $propertyConfig['decode'], $transformEvent);

                if (is_array($value) && $propertyScopeConfig) {
                    $value = $this->getScopedData(
                        $value,
                        $propertyScopeConfig
                    );
                }
            }

            if ($isHidden) {
                continue;
            }

            if ($value === null) {
                if ($this->isIterablePropertyType($propertyConfig['type'])) {
                    $value = [];

                } elseif ($config['filter_null_values']) {
                    continue;
                }
            }

            $result[$propertyName] = $value;
        }

        return $result;
    }

    /**
     * @param ClassMetadata $metadata
     * @param               $propertyName
     * @param               $propertyConfig
     * @param               $propertyScopeConfig
     * @param               $id
     * @return array|array[]
     * @throws InvalidConfigurationException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws InvalidQueryException
     * @throws NoResultException
     */
    private function fetchMissingAssociationRecursive(ClassMetadata $metadata,
                                                      $propertyName,
                                                      $propertyConfig,
                                                      $propertyScopeConfig,
                                                      $id): array
    {
        $associationMapping = $metadata->getAssociationMapping($propertyName);
        $databaseFields     = $this->dataSchemaService->getDatabaseFields(
            $propertyConfig,
            $propertyScopeConfig
        );
        $conditions         = $propertyConfig['conditions'];
        $orderByExpressions = $associationMapping['orderBy'] ?? [];

        switch ($associationMapping['type']) {
            case ClassMetadata::MANY_TO_MANY:
                $modelData = $this->persister->getManyToManyData(
                    $associationMapping,
                    $id,
                    $databaseFields,
                    $conditions,
                    $orderByExpressions
                );

                return array_map(
                    function ($itemData) use ($propertyConfig, $propertyScopeConfig) {
                        return $this->fetchMissingPropertiesRecursive(
                            $itemData,
                            $propertyConfig,
                            $propertyScopeConfig
                        );
                    },
                    $modelData
                );

            case ClassMetadata::ONE_TO_MANY:
                $modelData = $this->persister->getOneToManyData(
                    $associationMapping,
                    $id,
                    $databaseFields,
                    $conditions,
                    $orderByExpressions
                );

                return array_map(
                    function ($itemData) use ($propertyConfig, $propertyScopeConfig) {
                        return $this->fetchMissingPropertiesRecursive(
                            $itemData,
                            $propertyConfig,
                            $propertyScopeConfig
                        );
                    },
                    $modelData
                );

            case ClassMetadata::MANY_TO_ONE:
                $modelData = $this->persister->getManyToOneData(
                    $associationMapping,
                    $id,
                    $databaseFields,
                    $conditions
                );

                return $this->fetchMissingPropertiesRecursive(
                    $modelData,
                    $propertyConfig,
                    $propertyScopeConfig
                );

            case ClassMetadata::ONE_TO_ONE:
                $modelData = $this->persister->getOneToOneData(
                    $associationMapping,
                    $id,
                    $databaseFields,
                    $conditions
                );

                return $this->fetchMissingPropertiesRecursive(
                    $modelData,
                    $propertyConfig,
                    $propertyScopeConfig
                );
        }

        return [];
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @param array      $data
     * @param array|null $config
     * @param array|null $scopeConfig
     * @param array|null $defaultData
     * @return array
     * @throws DataTransformerNotExists
     * @throws InvalidConfigurationException
     * @throws InvalidQueryException
     * @throws MappingException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getData(array $data,
                            array $config = null,
                            array $scopeConfig = null,
                            ?array $defaultData = []): array
    {
        $this->dataSchemaService->startStopwatch('getData');

        $config      = $config ?? $this->configuration;
        $scopeConfig = $scopeConfig ?? $this->scopeConfig;

        if ($config !== $this->configuration || $scopeConfig !== $this->scopeConfig) {
            $config = $this->dataSchemaFilter->filter($config, $scopeConfig, $this->nestingDepth);
        }

        if (!$data) {
            return $defaultData;
        }

        if (!$config['properties']) {
            return $defaultData;
        }

        $fetchedData = $this->fetchMissingPropertiesRecursive($data, $config, $scopeConfig);

        $modifiedData = $this->modifyPropertiesRecursive($fetchedData, $config, $scopeConfig);

        $this->dataSchemaService->stopStopwatch('getData');

        return $modifiedData;
    }

    /**
     * @param array      $list
     * @param array|null $config
     * @param array|null $scopeConfig
     * @return array
     * @throws DataTransformerNotExists
     * @throws InvalidConfigurationException
     * @throws InvalidQueryException
     * @throws MappingException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getList(array $list, array $config = null, array $scopeConfig = null): array
    {
        $this->dataSchemaService->startStopwatch('getList');

        foreach ($list as $key => $value) {
            $list[$key] = $this->getData(
                $value,
                $config,
                $scopeConfig,
                null
            );

            $this->dataSchemaService->lapStopwatch('getList');
        }

        $this->dataSchemaService->stopStopwatch('getList');

        return $list;
    }

    /**
     * @return array
     */
    public function getQuerySelects(array $config = null): array
    {
        $config = $config ?? $this->configuration;

        return $config['query']['selects'] ?? [];
    }

    /**
     * @param string $propertyName
     * @return array|null
     */
    public function getPropertyConfiguration(string $propertyName): ?array
    {
        $propertyConfiguration = $this->configuration;

        $propertyNameParts = explode('.', $propertyName);
        foreach ($propertyNameParts as $propertyNamePart) {
            if (!isset($propertyConfiguration['properties'][$propertyNamePart])) {
                return null;
            }

            $propertyConfiguration = $propertyConfiguration['properties'][$propertyNamePart];
        }

        return $propertyConfiguration;
    }

    /**
     * @return string|int|null
     */
    public function getHydrationMode()
    {
        return $this->configuration['hydration_mode'] ?? $this->defaultHydratorMode;
    }

    /**
     * @param array $data
     * @param array $scope
     * @return array
     */
    protected function getScopedData(array $data, array $scope): array
    {
        $scopedData = [];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $scope)) {
                if (is_array($value) && $scope[$key]) {
                    $scopedData[$key] = $this->getScopedData($value, $scope[$key]);

                } else {
                    $scopedData[$key] = $value;
                }
            }
        }

        return $scopedData;
    }

    /**
     * @param string|null $type
     * @return bool
     */
    private function isIterablePropertyType(?string $type): bool
    {
        return in_array($type, ['array', 'json_array', 'collection']);
    }

    /**
     * @param string     $dataSchemaFile
     * @param array      $configuration
     * @param int        $nestingDepth
     * @param array|null $scopeConfig
     * @return array
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    private function getNestedDataSchemaConfiguration(string $dataSchemaFile,
                                                      array $configuration,
                                                      int $nestingDepth,
                                                      array $scopeConfig = null): array
    {

        $dataSchema = $this->dataSchemaFactory->createNestedDataSchema(
            $dataSchemaFile,
            $configuration,
            $scopeConfig,
            $nestingDepth
        );

        return $dataSchema->getConfiguration();
    }

    /**
     * @param array $array
     * @return bool
     */
    private function isOnlyNullInArray(array $array): bool
    {
        foreach ($array as $item) {
            if ($item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $config
     * @param array $data
     * @return string
     */
    private function getDiscriminatorValue(array $config, array $data): string
    {
        if (!$config['hasSubclasses']) {
            throw new \InvalidArgumentException("Only class configurations with subclasses may have discriminator");
        }

        $discriminatorColumnName = $config['discriminatorColumnName'];

        if (empty($data[$discriminatorColumnName])) {
            throw new \InvalidArgumentException("Discriminator field \"$discriminatorColumnName\" must have value");
        }

        return $data[$discriminatorColumnName];
    }

    /**
     * @param array $config
     * @param array $data
     * @return string
     */
    private function getDataClassName(array $config, array $data): string
    {
        $class = $config['class'];

        if ($config['hasSubclasses']) {
            $discriminator = $this->getDiscriminatorValue($config, $data);
            $class         = $config['discriminatorMap'][$discriminator];
        }

        return $class;
    }
}
