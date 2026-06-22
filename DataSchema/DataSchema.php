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
use Glavweb\DataSchemaBundle\Configuration\DataSchemaConfiguration;
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
 * Class DataSchema.
 *
 * @author  Andrey Nilov <nilov@glavweb.ru>
 */
class DataSchema
{
    /**
     * @var PersisterInterface
     */
    private $persister;

    /**
     * @var mixed[]
     */
    private array $configuration;

    /**
     * DataSchema constructor.
     *
     * @param array<string, mixed> $configuration
     *
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    public function __construct(private readonly DataSchemaFactory $dataSchemaFactory,
        private readonly DataSchemaService $dataSchemaService,
        private readonly DataSchemaFilter $dataSchemaFilter,
        PersisterFactory $persisterFactory,
        private readonly Placeholder $placeholder,
        private readonly ObjectHydrator $objectHydrator,
        array $configuration,
        private ?array $scopeConfig = null,
        private readonly ?string $queryLanguage = null,
        private readonly ?int $nestingDepth = null,
        array $path = [],
        private readonly ?string $defaultHydratorMode = null)
    {
        $this->persister = $persisterFactory->createPersister($configuration['db_driver'], $this);

        $this->dataSchemaService->startStopwatch('filter');

        $configuration = $this->dataSchemaFilter->filter($configuration, $this->scopeConfig, $this->nestingDepth);

        $this->dataSchemaService->stopStopwatch('filter');
        $this->dataSchemaService->startStopwatch('prepareConfiguration');

        $isRoot = $path === [];
        $path[] = ['type' => 'schema', 'name' => $configuration['schema']];

        $this->configuration =
            $this->prepareConfiguration($configuration, $configuration['class'], $this->scopeConfig, $this->nestingDepth, $path);

        if ($isRoot) {
            $this->configuration = $this->dataSchemaService->reconfigureByExtensions(
                $this->configuration,
                $this->configuration,
                $this->scopeConfig,
                $this->queryLanguage
            );
        }

        $this->dataSchemaService->stopStopwatch('prepareConfiguration');
    }

    public function hasProperty(string $propertyName): bool
    {
        return $this->getPropertyConfiguration($propertyName) !== null;
    }

    public function hasPropertyInDb(string $propertyName): bool
    {
        $propertyConfiguration = $this->getPropertyConfiguration($propertyName);

        return $propertyConfiguration !== null && isset($propertyConfiguration['from_db'])
            && $propertyConfiguration['from_db'];
    }

    public function conditionPlaceholder(string $condition, string $alias, ?UserInterface $user = null): string
    {
        return $this->placeholder->condition($condition, $alias, $user);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    protected function prepareConfiguration(array $configuration,
        ?string $class,
        ?array $scopeConfig = null,
        int $nestingDepth = 0,
        array $path = []): array
    {
        if ($nestingDepth < 0) {
            throw new InvalidConfigurationException($configuration, "Maximum nesting depth exceeded {$this->pathToString($path)}");
        }

        $class ??= $configuration['class'] ?? null;

        $configuration = array_replace(DataSchemaConfiguration::PROPERTIES_DEFAULT_VALUES, $configuration);
        $configuration['class'] = $class;

        if (!$this->dataSchemaFilter->isGranted($configuration['roles'])) {
            return [];
        }

        // class
        $classMetadata = $class ? $this->dataSchemaService->getClassMetadata($class) : null;
        $identifierFieldNames = [];
        $discriminatorMap = null;

        if ($classMetadata instanceof ClassMetadata) {
            if ($classMetadata->subClasses) {
                $configuration['hasSubclasses'] = true;
                $configuration['discriminatorColumnName'] = $classMetadata->discriminatorColumn['name'];
                $configuration['discriminatorMap'] = $classMetadata->discriminatorMap;
                $discriminatorMap = $configuration['discriminatorMap'];
            }

            $configuration['tableName'] = $classMetadata->getTableName();
            $identifierFieldNames = $classMetadata->getIdentifierFieldNames();
        }

        $configProperties = $configuration['properties'] ?? [];
        $properties = [];

        foreach ($identifierFieldNames as $idName) {
            if (!\array_key_exists($idName, $configProperties)) {
                $configProperties[$idName] = array_merge(DataSchemaConfiguration::PROPERTIES_DEFAULT_VALUES, ['hidden' => true]);
                $configuration['properties'][$idName] = $configProperties[$idName];
            }
        }

        foreach ($configProperties as $propertyName => $propertyConfig) {
            $propertyScopeConfig = $scopeConfig[$propertyName] ?? null;
            $schema = $propertyConfig['schema'] ?? null;
            $isNested = $this->dataSchemaService->isNestedProperty($propertyConfig);
            $propertyPath = array_merge($path, [['type' => 'property', 'name' => $propertyName]]);

            if ($schema) {
                $propertyConfig = $this->getNestedDataSchemaConfiguration(
                    $schema,
                    $propertyConfig,
                    $nestingDepth - 1,
                    $propertyPath,
                    $propertyScopeConfig
                );
            }

            $discriminator = $propertyConfig['discriminator'] ?? null;
            $subClass = $discriminatorMap && $discriminator ? $discriminatorMap[$discriminator] ?? null : null;

            $propertyOwnerClassMetadata =
                $subClass ? $this->dataSchemaService->getClassMetadata($subClass) : $classMetadata;

            // set default description
            if (empty($propertyConfig['description']) && $propertyOwnerClassMetadata instanceof ClassMetadata
                && $propertyOwnerClassMetadata->hasField($propertyName)) {
                $fieldMapping = $propertyOwnerClassMetadata->getFieldMapping($propertyName);
                $description = $fieldMapping['options']['comment'] ?? null;

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
                    $nestingDepth - 1,
                    $propertyPath
                );
                if ($propertyConfig && $propertyOwnerClassMetadata instanceof ClassMetadata
                    && $propertyOwnerClassMetadata->hasAssociation($propertyName)) {
                    $isCollection = $propertyOwnerClassMetadata->isCollectionValuedAssociation($propertyName);

                    $propertyConfig['type'] = $isCollection ? 'collection' : 'entity';
                }
            } elseif ($propertyOwnerClassMetadata instanceof ClassMetadata) {
                $propertyConfig['type'] ??= $propertyOwnerClassMetadata->getTypeOfField($propertyName);
                $propertyConfig['from_db'] = $propertyOwnerClassMetadata->hasField($propertyName);
                $propertyConfig['field_db_name'] =
                    $propertyConfig['from_db'] ? $propertyOwnerClassMetadata->getColumnName($propertyName) : null;
            }

            $properties[$propertyName] = $propertyConfig;
        }

        $configuration['properties'] = $properties;

        return $configuration;
    }

    public function addPropertyFromSelect(string $name, string $source): void
    {
        $this->configuration['properties'][$name] = array_merge(DataSchemaConfiguration::PROPERTIES_DEFAULT_VALUES, ['source' => $source]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfigurationException
     * @throws MappingException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws InvalidQueryException
     */
    private function fetchMissingPropertiesRecursive(array $data, array $config, ?array $scopeConfig = null): array
    {
        if ($this->isOnlyNullInArray($data)) {
            return $data;
        }

        $id = $data['id'] ?? null;
        $class = $this->getDataClassName($config, $data);
        $discriminator = $config['hasSubclasses'] ? $this->getDiscriminatorValue($config, $data) : null;
        $metadata = $this->dataSchemaService->getClassMetadata($class);

        $result = $data;
        $fields = [];

        foreach ($config['properties'] as $propertyName => $propertyConfig) {
            $propertyScopeConfig = $scopeConfig[$propertyName] ?? [];
            $propertyDiscriminator = $propertyConfig['discriminator'] ?? null;
            $isNested = $this->dataSchemaService->isNestedProperty($propertyConfig);
            $isFromDb = $propertyConfig['from_db'] ?? false;
            $ignoreDiscriminatorMismatch = $propertyConfig['ignore_discriminator_mismatch'];
            $source = $propertyConfig['source'] ?? null;

            $value = null;

            if ($discriminator && $propertyDiscriminator && $discriminator !== $propertyDiscriminator
                && !(
                    $ignoreDiscriminatorMismatch
                    && $this->dataSchemaService->hasPropertyInSubclasses($config['class'], $propertyName)
                )) {
                continue;
            }

            if ($source) {
                if (\array_key_exists($propertyName, $data)) {
                    $value = $data[$propertyName];
                } elseif ($source !== DataSchemaConfiguration::SOURCE_SELF_TOKEN && $source === $propertyName) {
                    $querySelects = $this->getQuerySelects($config);
                    $select = $querySelects[$source] ?? null;

                    if ($select) {
                        $value = $id !== null ? $this->persister->getSelectQueryResult($class, $select, $id) : null;
                    }
                }
            } elseif (\array_key_exists($propertyName, $data)) {
                $value = $data[$propertyName];

                if ($isNested && \is_array($value)) {
                    if ($this->isIterablePropertyType($propertyConfig['type'])) {
                        $value = array_map(
                            fn (array $itemData): array => $this->fetchMissingPropertiesRecursive(
                                $itemData,
                                $propertyConfig,
                                $propertyScopeConfig
                            ),
                            $value
                        );
                    } else {
                        $value = $this->fetchMissingPropertiesRecursive($value, $propertyConfig, $propertyScopeConfig);
                    }
                }
            } elseif ($isNested) {
                if (!$id) {
                    continue;
                }

                if (!$metadata->hasAssociation($propertyName)) {
                    continue;
                }

                $value = $this->fetchMissingAssociationRecursive(
                    $metadata,
                    $propertyName,
                    $propertyConfig,
                    $propertyScopeConfig,
                    $id
                );
            } elseif ($isFromDb && $metadata->hasField($propertyName)) {
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
     * @param array<string, mixed> $config
     *
     * @throws DataTransformerNotExists
     */
    private function modifyPropertiesRecursive(array $data,
        array $config,
        ?array $scopeConfig = null,
        ?string $parentClassName = null,
        ?string $parentPropertyName = null): array
    {
        $class = $this->getDataClassName($config, $data);
        $discriminator = $config['hasSubclasses'] ? $this->getDiscriminatorValue($config, $data) : null;
        $selects = $this->getQuerySelects($config);

        $result = [];

        foreach ($config['properties'] as $propertyName => $propertyConfig) {
            $value = null;
            $propertyScopeConfig = $scopeConfig[$propertyName] ?? [];
            $propertyDiscriminator = $propertyConfig['discriminator'] ?? null;
            $isHidden = $propertyConfig['hidden'] ?? false;
            $source = $propertyConfig['source'] ?? null;
            $decode = $propertyConfig['decode'] ?? null;
            $ignoreDiscriminatorMismatch = $propertyConfig['ignore_discriminator_mismatch'];

            if ($discriminator && $propertyDiscriminator && $discriminator !== $propertyDiscriminator
                && !(
                    $ignoreDiscriminatorMismatch
                    && $this->dataSchemaService->hasPropertyInSubclasses($config['class'], $propertyName)
                )) {
                continue;
            }

            if ($source && $source !== DataSchemaConfiguration::SOURCE_SELF_TOKEN) {
                $isPostModificationSource = !empty($config['properties'][$source]['source']) && !\array_key_exists($source, $selects);

                if ($isPostModificationSource ? !\array_key_exists($source, $result) : !\array_key_exists($source, $data)) {
                    throw new \RuntimeException("Property \"{$source}\" must be defined.");
                }

                $value = $isPostModificationSource ? $result[$source] : $data[$source];
            } elseif (\array_key_exists($propertyName, $data)) {
                $value = $data[$propertyName];
            }

            if (\is_array($value)) {
                if (!\array_key_exists('type', $propertyConfig)) {
                    throw new \RuntimeException('Property "type" must be defined.');
                }

                if ($propertyConfig['type'] === 'entity') {
                    if ($this->isOnlyNullInArray($value) && $config['filter_null_values']) {
                        $value = null;
                    } else {
                        $value = $this->modifyPropertiesRecursive(
                            $value,
                            $propertyConfig,
                            $propertyScopeConfig,
                            $class,
                            $propertyName
                        );
                    }
                } elseif ($propertyConfig['type'] === 'collection') {
                    $value = array_map(
                        fn (array $itemData): array => $this->modifyPropertiesRecursive(
                            $itemData,
                            $propertyConfig,
                            $propertyScopeConfig,
                            $class,
                            $propertyName
                        ),
                        $value
                    );
                }
            }

            if ($decode) {
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

                if ($source === DataSchemaConfiguration::SOURCE_SELF_TOKEN) {
                    $value = $data;
                }

                $value = $this->dataSchemaService->decode($value, $decode, $transformEvent);

                if (\is_array($value) && $propertyScopeConfig) {
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
     * @param array<string, mixed> $propertyConfig
     *
     * @return array|array[]
     *
     * @throws InvalidConfigurationException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws InvalidQueryException
     * @throws NoResultException
     */
    private function fetchMissingAssociationRecursive(ClassMetadata $metadata,
        string $propertyName,
        array $propertyConfig,
        ?array $propertyScopeConfig,
        $id): array
    {
        $associationMapping = $metadata->getAssociationMapping($propertyName);
        $databaseFields = $this->dataSchemaService->getDatabaseFields(
            $propertyConfig,
            $propertyScopeConfig
        );
        $conditions = $propertyConfig['conditions'];
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
                    fn (array $itemData): array => $this->fetchMissingPropertiesRecursive(
                        $itemData,
                        $propertyConfig,
                        $propertyScopeConfig
                    ),
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
                    fn (array $itemData): array => $this->fetchMissingPropertiesRecursive(
                        $itemData,
                        $propertyConfig,
                        $propertyScopeConfig
                    ),
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
     * @return mixed[]
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @throws DataTransformerNotExists
     * @throws InvalidConfigurationException
     * @throws InvalidQueryException
     * @throws MappingException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getData(array $data,
        ?array $config = null,
        ?array $scopeConfig = null,
        ?array $defaultData = []): array
    {
        $this->dataSchemaService->startStopwatch('getData');

        $config ??= $this->configuration;
        $scopeConfig ??= $this->scopeConfig;

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
     * @throws DataTransformerNotExists
     * @throws InvalidConfigurationException
     * @throws InvalidQueryException
     * @throws MappingException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getList(array $list, ?array $config = null, ?array $scopeConfig = null): array
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
     * @param array|array[] $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function setScopeConfig(?array $scopeConfig): void
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getQuerySelects(?array $config = null): array
    {
        $config ??= $this->configuration;

        return $config['query']['selects'] ?? [];
    }

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

    public function getPropertyScopeConfiguration(string $propertyName): ?array
    {
        $propertyScopeConfig = $this->scopeConfig;

        $propertyNameParts = explode('.', $propertyName);
        foreach ($propertyNameParts as $propertyNamePart) {
            if (!isset($propertyScopeConfig[$propertyNamePart])) {
                return null;
            }

            $propertyScopeConfig = $propertyScopeConfig[$propertyNamePart];
        }

        return $propertyScopeConfig;
    }

    public function modifyConfiguration(callable $modify): array
    {
        return $this->configuration = $modify($this->configuration);
    }

    public function modifyPropertyConfiguration(string $propertyPath, callable $modify): array
    {
        $configuration = $this->configuration;
        $propertyConfiguration = &$configuration;

        $properties = null;
        $propertyKey = null;
        $propertyPathParts = explode('.', $propertyPath);
        foreach ($propertyPathParts as $propertyName) {
            if (!isset($propertyConfiguration['properties'][$propertyName])) {
                throw new \InvalidArgumentException('Property "'.$propertyPath.'" does not exist.');
            }

            $properties = &$propertyConfiguration['properties'];
            $propertyKey = $propertyName;
            $propertyConfiguration = &$propertyConfiguration['properties'][$propertyKey];
        }

        $properties[$propertyKey] = $modify($propertyConfiguration);

        $this->configuration = $configuration;

        return $properties[$propertyKey];
    }

    public function enablePropertyCondition(string $propertyPath, string $conditionName): self
    {
        $this->setPropertyConditionEnabled($propertyPath, $conditionName, true);

        return $this;
    }

    public function disablePropertyCondition(string $propertyPath, string $conditionName): self
    {
        $this->setPropertyConditionEnabled($propertyPath, $conditionName, false);

        return $this;
    }

    /**
     * @return $this
     */
    public function setPropertyOrderBy(string $propertyPath, string $orderByPropertyName, string $order): self
    {
        if (!\in_array(strtolower($order), ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException("Order option \"{$order}\" for property \"{$propertyPath}\" is not allowed.");
        }

        $this->modifyPropertyConfiguration($propertyPath, static function (array $configuration) use ($order, $orderByPropertyName): array {
            $configuration['orderBy'] = [$orderByPropertyName => $order];

            return $configuration;
        });

        return $this;
    }

    /**
     * @return $this
     */
    public function addPropertyOrderBy(string $propertyPath, string $orderByPropertyName, string $order): self
    {
        if (!\in_array(strtolower($order), ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException("Order option \"{$order}\" for property \"{$propertyPath}\" is not allowed.");
        }

        $this->modifyPropertyConfiguration($propertyPath, static function (array $configuration) use ($order, $orderByPropertyName): array {
            $configuration['orderBy'][$orderByPropertyName] = $order;

            return $configuration;
        });

        return $this;
    }

    protected function setPropertyConditionEnabled(string $propertyPath, string $conditionName, bool $enabled): self
    {
        $this->modifyPropertyConfiguration(
            $propertyPath,
            static function (array $config) use ($enabled, $propertyPath, $conditionName): array {
                if (!isset($config['conditions'][$conditionName])) {
                    throw new \InvalidArgumentException("Condition '{$conditionName}' for property '{$propertyPath}' is not defined.");
                }

                $config['conditions'][$conditionName]['enabled'] = $enabled;

                return $config;
            }
        );

        return $this;
    }

    /**
     * @return string|int|null
     */
    public function getHydrationMode()
    {
        return $this->configuration['hydration_mode'] ?? $this->defaultHydratorMode;
    }

    protected function getScopedData(array $data, array $scope): array
    {
        $scopedData = [];

        foreach ($data as $key => $value) {
            if (\array_key_exists($key, $scope)) {
                $scopedData[$key] = \is_array($value) && $scope[$key] ? $this->getScopedData($value, $scope[$key]) : $value;
            }
        }

        return $scopedData;
    }

    private function isIterablePropertyType(?string $type): bool
    {
        return \in_array($type, ['array', 'json_array', 'collection'], true);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    private function getNestedDataSchemaConfiguration(string $dataSchemaFile,
        array $configuration,
        int $nestingDepth,
        array $path,
        ?array $scopeConfig = null): array
    {
        $dataSchema = $this->dataSchemaFactory->createNestedDataSchema(
            $dataSchemaFile,
            $configuration,
            $nestingDepth,
            $path,
            $scopeConfig
        );

        return $dataSchema->getConfiguration();
    }

    private function isOnlyNullInArray(array $array): bool
    {
        return array_all($array, static fn ($item): bool => $item === null);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function getDiscriminatorValue(array $config, array $data): string
    {
        if (!$config['hasSubclasses']) {
            throw new \InvalidArgumentException('Only class configurations with subclasses may have discriminator');
        }

        $discriminatorColumnName = $config['discriminatorColumnName'];

        if (empty($data[$discriminatorColumnName])) {
            throw new \InvalidArgumentException("Discriminator field \"{$discriminatorColumnName}\" must have value");
        }

        return $data[$discriminatorColumnName];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function getDataClassName(array $config, array $data): string
    {
        $class = $config['class'];

        if ($config['hasSubclasses']) {
            $discriminator = $this->getDiscriminatorValue($config, $data);
            $class = $config['discriminatorMap'][$discriminator];
        }

        return $class;
    }

    private function pathToString(array $path): string
    {
        $result = '';

        foreach ($path as $i => $item) {
            $prefix = $i === 0 ? '' : ($item['type'] === 'property' ? '::' : ' > ');

            $result .= "{$prefix}{$item['name']}";
        }

        return $result;
    }
}
