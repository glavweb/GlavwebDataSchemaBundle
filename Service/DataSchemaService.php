<?php

namespace Glavweb\DataSchemaBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Mapping\ClassMetadata;
use Glavweb\DataSchemaBundle\Configuration\DataSchemaConfiguration;
use Glavweb\DataSchemaBundle\DataTransformer\DataTransformerInterface;
use Glavweb\DataSchemaBundle\DataTransformer\DataTransformerRegistry;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationException;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationPropertyException;
use Glavweb\DataSchemaBundle\Exception\DataTransformer\DataTransformerNotExists;
use Glavweb\DataSchemaBundle\Loader\Yaml\DataSchemaYamlLoader;
use Glavweb\DataSchemaBundle\Loader\Yaml\ScopeYamlLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Class DataSchemaService
 *
 * @package Glavweb\DataSchemaBundle\Service
 *
 * @author  Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class DataSchemaService
{

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var int
     */
    private $nestingDepth;

    /**
     * @var DataTransformerRegistry
     */
    private $dataTransformerRegistry;

    /**
     * @var FileLocator
     */
    private $scopeFileLocator;

    /**
     * @var FileLocator
     */
    private $dataSchemaFileLocator;

    /**
     * @var array
     */
    private $dataSchemaConfigCache = [];

    /**
     * @var Stopwatch|null
     */
    private $stopwatch;

    /**
     * DataSchemaService constructor.
     */
    public function __construct(Registry $doctrine,
                                DataTransformerRegistry $dataTransformerRegistry,
                                string $dataSchemaDir,
                                string $scopeDir,
                                int $nestingDepth,
                                ?Stopwatch $stopwatch)
    {
        $this->doctrine                = $doctrine;
        $this->dataTransformerRegistry = $dataTransformerRegistry;
        $this->nestingDepth            = $nestingDepth;
        $this->dataSchemaFileLocator   = new FileLocator($dataSchemaDir);
        $this->scopeFileLocator        = new FileLocator($scopeDir);
        $this->stopwatch               = $stopwatch;
    }

    /**
     * @param array $configuration
     * @return array
     * @throws InvalidConfigurationException
     */
    public function processSchemaConfiguration(array $configuration): array
    {
        $processor = new Processor();

        $dataSchemaConfiguration = new DataSchemaConfiguration($this->nestingDepth);

        try {
            return $processor->processConfiguration(
                $dataSchemaConfiguration,
                [$configuration]
            );
        } catch (\Exception $e) {
            throw new InvalidConfigurationException($configuration, $e->getMessage());
        }
    }

    /**
     * @param string $dataSchemaFile
     * @return array
     */
    public function loadSchemaConfigurationFromFile(string $dataSchemaFile): array
    {
        $dataSchemaLoader = new DataSchemaYamlLoader($this->dataSchemaFileLocator);
        $dataSchemaLoader->load($dataSchemaFile);

        return $dataSchemaLoader->getConfiguration();
    }

    /**
     * @param string $scopeFile
     * @return array
     */
    public function loadScopeConfiguration(string $scopeFile): array
    {
        $scopeLoader = new ScopeYamlLoader($this->scopeFileLocator);
        $scopeLoader->load($scopeFile);

        return $scopeLoader->getConfiguration();
    }

    /**
     * @param string $decodeString
     * @return array dataTransformerNames
     */
    public function parseDecodeString(string $decodeString): array
    {
        $dataTransformerNames = explode('|', $decodeString);

        return array_map('trim', $dataTransformerNames);
    }

    /**
     * @param string $name
     */
    public function startStopwatch(string $name): void
    {
        if ($this->stopwatch) {
            $this->stopwatch->start($name, 'GlavwebDataSchemaBundle');
        }
    }

    /**
     * @param string $name
     */
    public function stopStopwatch(string $name): void
    {
        if ($this->stopwatch) {
            $this->stopwatch->stop($name);
        }
    }

    /**
     * @param string $name
     */
    public function lapStopwatch(string $name): void
    {
        if ($this->stopwatch) {
            $this->stopwatch->lap($name);
        }
    }

    public function isDataSchemaFileExists(string $dataSchemaFile): bool
    {
        try {
            $this->dataSchemaFileLocator->locate($dataSchemaFile);
        } catch (FileLocatorFileNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $dataSchemaFile
     * @return array
     * @throws InvalidConfigurationException
     */
    public function getConfigurationFromFile(string $dataSchemaFile): array
    {
        if (isset($this->dataSchemaConfigCache[$dataSchemaFile])) {
            return $this->dataSchemaConfigCache[$dataSchemaFile];
        }

        $dataSchemaConfig = $this->loadSchemaConfigurationFromFile($dataSchemaFile);

        $dataSchemaConfig['schema'] = $dataSchemaFile;

        $dataSchemaConfig = $this->processSchemaConfiguration($dataSchemaConfig);

        $this->dataSchemaConfigCache[$dataSchemaFile] = $dataSchemaConfig;

        return $dataSchemaConfig;
    }

    /**
     * @param array  $configuration
     * @param string $propertyName
     * @return array
     * @throws InvalidConfigurationException
     */
    public function getPropertySourcesStack(array $configuration, string $propertyName): array
    {
        $depth           = 0;
        $propertiesStack = [];
        $selects         = $configuration['query']['selects'] ?? [];
        $propertyConfig  = $configuration['properties'][$propertyName] ?? null;

        try {
            while ($currentPropertyName = $propertyConfig['source'] ?? null) {
                if (array_key_exists($currentPropertyName, $selects)) {
                    break;
                }

                if ($currentPropertyName === $propertyName) {
                    throw new InvalidConfigurationPropertyException(
                        $propertyName, "Shouldn't refer to self in \"source\" option"
                    );
                }

                $propertyConfig = $configuration['properties'][$currentPropertyName] ?? null;

                if (!$propertyConfig) {
                    throw new InvalidConfigurationPropertyException(
                        $propertyName, "Invalid \"source\" option. Referred property \"$currentPropertyName\" doesn't exist in configuration."
                    );
                }

                $propertiesStack[] = [$currentPropertyName, $propertyConfig];

                if (++$depth > 10) {
                    throw new InvalidConfigurationPropertyException(
                        $propertyName, "Maximum referencing depth exceeded"
                    );
                }

            }
        } catch (InvalidConfigurationPropertyException $e) {
            $propertiesStackString = 'Sources stack: ' . implode(
                    ' > ',
                    [$propertyName] + array_column($propertiesStack, 0)
                );

            throw new InvalidConfigurationException($configuration, $propertiesStackString . '. ' . $e->getMessage());
        }

        return $propertiesStack;
    }

    /**
     * @param array      $entityConfig
     * @param array|null $scopeConfig
     * @return array
     * @throws InvalidConfigurationException
     */
    public function getDatabaseFields(array $entityConfig, array $scopeConfig = null): array
    {
        $properties       = $entityConfig['properties'];
        $entityClass      = $entityConfig['class'];
        $discriminatorMap = $entityConfig['discriminatorMap'] ?? null;
        $databaseFields   = [];

        foreach ($properties as $propertyName => $propertyConfig) {
            if (isset($propertyConfig['discriminator']) && $discriminatorMap
                && $discriminatorMap[$propertyConfig['discriminator']] !== $entityClass) {
                continue;
            }
            if (!$propertyConfig['hidden'] && $scopeConfig && !array_key_exists($propertyName, $scopeConfig)) {
                continue;
            }

            $propertySourcesStack = $this->getPropertySourcesStack($entityConfig, $propertyName);

            $isVirtualProperty = !empty($propertySourcesStack);

            if ($isVirtualProperty) {
                foreach ($propertySourcesStack as [$sourcePropertyName, $sourcePropertyData]) {
                    $isValid = $sourcePropertyData['from_db'] ?? false;

                    if ($isValid && !in_array($sourcePropertyName, $databaseFields, true)) {
                        $databaseFields[] = $sourcePropertyName;
                    }
                }
            } else {
                $isValid = $propertyConfig['from_db'] ?? false;

                if ($isValid && !in_array($propertyName, $databaseFields, true)) {
                    $databaseFields[] = $propertyName;
                }
            }
        }

        return $databaseFields;
    }

    /**
     * @param array $propertyConfiguration
     * @return bool
     */
    public function isNestedProperty(array $propertyConfiguration): bool
    {
        $schema     = $propertyConfiguration['schema'] ?? null;
        $class      = $propertyConfiguration['class'] ?? null;
        $properties = $propertyConfiguration['properties'] ?? [];

        return $schema || $class || $properties;
    }

    /**
     * @param string $class
     * @return ClassMetadata
     */
    public function getClassMetadata(string $class): ClassMetadata
    {
        return $this->doctrine->getManager()->getClassMetadata($class);
    }

    /**
     * @param string $class
     * @param string $propertyName
     * @return bool
     */
    public function hasPropertyInSubclasses(string $class, string $propertyName): bool
    {
        $superClassMetadata   = $this->getClassMetadata($class);

        foreach ($superClassMetadata->subClasses as $subClass) {
            $subClassMetadata = $this->getClassMetadata($subClass);

            if (in_array($propertyName, $subClassMetadata->getFieldNames(), true)) {
                return true;
            }

            if (in_array($propertyName, $subClassMetadata->getAssociationNames(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws DataTransformerNotExists
     */
    public function getDataTransformer(string $name): DataTransformerInterface
    {
        if (!$this->dataTransformerRegistry->has($name)) {
            throw new DataTransformerNotExists($name);
        }

        return $this->dataTransformerRegistry->get($name);
    }
}