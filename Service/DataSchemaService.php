<?php

namespace Glavweb\DataSchemaBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Mapping\ClassMetadata;
use Glavweb\DataSchemaBundle\ConfigTransformer\ConfigTransformerRegistry;
use Glavweb\DataSchemaBundle\ConfigTransformer\ConfigTransformEvent;
use Glavweb\DataSchemaBundle\Configuration\DataSchemaConfiguration;
use Glavweb\DataSchemaBundle\DataTransformer\DataTransformerInterface;
use Glavweb\DataSchemaBundle\DataTransformer\DataTransformerRegistry;
use Glavweb\DataSchemaBundle\DataTransformer\TransformEvent;
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
 * Class DataSchemaService.
 *
 * @author  Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class DataSchemaService
{
    private readonly FileLocator $scopeFileLocator;

    private readonly FileLocator $dataSchemaFileLocator;

    private array $dataSchemaConfigCache = [];

    /**
     * DataSchemaService constructor.
     */
    public function __construct(private readonly Registry $doctrine,
        private readonly DataTransformerRegistry $dataTransformerRegistry,
        private readonly ConfigTransformerRegistry $configTransformerRegistry,
        string $dataSchemaDir,
        string $scopeDir,
        private readonly int $nestingDepth,
        private readonly ?Stopwatch $stopwatch)
    {
        $this->dataSchemaFileLocator = new FileLocator($dataSchemaDir);
        $this->scopeFileLocator = new FileLocator($scopeDir);
    }

    /**
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
            throw new InvalidConfigurationException($configuration, $e->getMessage(), $e, $e);
        }
    }

    public function loadSchemaConfigurationFromFile(string $dataSchemaFile): array
    {
        $dataSchemaLoader = new DataSchemaYamlLoader($this->dataSchemaFileLocator);
        $dataSchemaLoader->load($dataSchemaFile);

        return $dataSchemaLoader->getConfiguration();
    }

    public function loadScopeConfiguration(string $scopeFile): array
    {
        $scopeLoader = new ScopeYamlLoader($this->scopeFileLocator);
        $scopeLoader->load($scopeFile);

        return $scopeLoader->getConfiguration();
    }

    /**
     * @return array dataTransformerNames
     */
    public function parseDecodeString(string $decodeString): array
    {
        $dataTransformerNames = explode('|', $decodeString);

        return array_map(trim(...), $dataTransformerNames);
    }

    public function startStopwatch(string $name): void
    {
        if ($this->stopwatch instanceof Stopwatch) {
            $this->stopwatch->start($name, 'GlavwebDataSchemaBundle');
        }
    }

    public function stopStopwatch(string $name): void
    {
        if ($this->stopwatch instanceof Stopwatch) {
            $this->stopwatch->stop($name);
        }
    }

    public function lapStopwatch(string $name): void
    {
        if ($this->stopwatch instanceof Stopwatch) {
            $this->stopwatch->lap($name);
        }
    }

    public function isDataSchemaFileExists(string $dataSchemaFile): bool
    {
        try {
            $this->dataSchemaFileLocator->locate($dataSchemaFile);
        } catch (FileLocatorFileNotFoundException) {
            return false;
        }

        return true;
    }

    /**
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
     * @param array<string, mixed> $configuration
     *
     * @throws InvalidConfigurationException
     */
    public function getPropertySourcesStack(array $configuration, string $propertyName): array
    {
        $depth = 0;
        $propertiesStack = [];
        $selects = $configuration['query']['selects'] ?? [];
        $propertyConfig = $configuration['properties'][$propertyName] ?? null;

        try {
            while ($currentPropertyName = $propertyConfig['source'] ?? null) {
                if ($currentPropertyName === DataSchemaConfiguration::SOURCE_SELF_TOKEN || \array_key_exists(
                    $currentPropertyName,
                    $selects
                )) {
                    break;
                }

                if ($currentPropertyName === $propertyName) {
                    throw new InvalidConfigurationPropertyException($propertyName, "Shouldn't refer to self in \"source\" option");
                }

                $propertyConfig = $configuration['properties'][$currentPropertyName] ?? null;

                if (!$propertyConfig) {
                    $message = "Invalid \"source\" option. Referred property \"{$currentPropertyName}\" doesn't exist in configuration.";
                    throw new InvalidConfigurationPropertyException($propertyName, $message);
                }

                if ($this->isNestedProperty($propertyConfig)) {
                    $message = "Invalid \"source\" option. Referred property \"{$currentPropertyName}\" should have scalar type.";
                    throw new InvalidConfigurationPropertyException($propertyName, $message);
                }

                $propertiesStack[] = [$currentPropertyName, $propertyConfig];

                if (++$depth > 10) {
                    throw new InvalidConfigurationPropertyException($propertyName, 'Maximum referencing depth exceeded');
                }
            }
        } catch (InvalidConfigurationPropertyException $e) {
            $propertiesStackString = 'Sources stack: '.implode(
                ' > ',
                [$propertyName] + array_column($propertiesStack, 0)
            );

            throw new InvalidConfigurationException($configuration, $propertiesStackString.'. '.$e->getMessage(), $e, $e);
        }

        return $propertiesStack;
    }

    /**
     * @param array<string, mixed> $entityConfig
     *
     * @throws InvalidConfigurationException
     */
    public function getDatabaseFields(array $entityConfig, ?array $scopeConfig = null): array
    {
        $properties = $entityConfig['properties'];
        $entityClass = $entityConfig['class'];
        $discriminatorMap = $entityConfig['discriminatorMap'] ?? null;
        $databaseFields = [];

        foreach ($properties as $propertyName => $propertyConfig) {
            if (isset($propertyConfig['discriminator']) && $discriminatorMap
                && $discriminatorMap[$propertyConfig['discriminator']] !== $entityClass) {
                continue;
            }

            if (!$propertyConfig['hidden'] && $scopeConfig && !\array_key_exists($propertyName, $scopeConfig)) {
                continue;
            }

            $propertySourcesStack = $this->getPropertySourcesStack($entityConfig, $propertyName);

            $isVirtualProperty = $propertySourcesStack !== [];

            if ($isVirtualProperty) {
                foreach ($propertySourcesStack as [$sourcePropertyName, $sourcePropertyData]) {
                    $isValid = $sourcePropertyData['from_db'] ?? false;

                    if ($isValid && !\in_array($sourcePropertyName, $databaseFields, true)) {
                        $databaseFields[] = $sourcePropertyName;
                    }
                }
            } else {
                $isValid = $propertyConfig['from_db'] ?? false;

                if ($isValid && !\in_array($propertyName, $databaseFields, true)) {
                    $databaseFields[] = $propertyName;
                }
            }
        }

        return $databaseFields;
    }

    /**
     * @param array<string, mixed> $propertyConfiguration
     */
    public function isNestedProperty(array $propertyConfiguration): bool
    {
        $schema = $propertyConfiguration['schema'] ?? null;
        $class = $propertyConfiguration['class'] ?? null;
        $properties = $propertyConfiguration['properties'] ?? [];

        return $schema || $class || $properties;
    }

    public function getClassMetadata(string $class): ClassMetadata
    {
        return $this->doctrine->getManager()->getClassMetadata($class);
    }

    public function hasPropertyInSubclasses(string $class, string $propertyName): bool
    {
        $superClassMetadata = $this->getClassMetadata($class);

        foreach ($superClassMetadata->subClasses as $subClass) {
            $subClassMetadata = $this->getClassMetadata($subClass);

            if (\in_array($propertyName, $subClassMetadata->getFieldNames(), true)) {
                return true;
            }

            if (\in_array($propertyName, $subClassMetadata->getAssociationNames(), true)) {
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

    /**
     * @throws DataTransformerNotExists
     */
    public function decode($value, string $decodeString, TransformEvent $transformEvent)
    {
        $dataTransformerNames = $this->parseDecodeString($decodeString);

        foreach ($dataTransformerNames as $dataTransformerName) {
            $transformer = $this->getDataTransformer($dataTransformerName);
            $value = $transformer->transform($value, $transformEvent);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array []
     */
    public function reconfigureByExtensions(array $config,
        array $rootConfig,
        ?array $scopeConfig = null,
        ?string $queryLanguage = null,
        array $path = []): array
    {
        $properties = $config['properties'] ?? [];

        foreach ($properties as $propertyName => $propertyConfig) {
            if ($this->isNestedProperty($propertyConfig)) {
                $propertyPath = array_merge($path, [$propertyName]);
                $config['properties'][$propertyName] = $this->reconfigureByExtensions(
                    $propertyConfig, $rootConfig, $scopeConfig, $queryLanguage, $propertyPath
                );
            }
        }

        $result = $config;

        foreach ($this->configTransformerRegistry->getAll() as $transformer) {
            $result = $transformer->transform($result, new ConfigTransformEvent($path, $rootConfig, $scopeConfig, $queryLanguage));
        }

        return $result;
    }
}
