<?php

namespace Glavweb\DataSchemaBundle\Service;

use Doctrine\ORM\Mapping\ClassMetadata;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationException;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationPropertyException;
use Glavweb\DataSchemaBundle\Exception\DataTransformer\DataTransformerNotExists;

class DataSchemaValidator
{
    /**
     * DataSchemaFilter constructor.
     */
    public function __construct(private readonly DataSchemaService $dataSchemaService)
    {
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function validateFile(string $dataSchemaFile, int $nestingDepth = 0): void
    {
        $configuration = $this->dataSchemaService->getConfigurationFromFile($dataSchemaFile);

        $this->validate($configuration, $nestingDepth);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public function validate(array $config, int $nestingDepth = 0, bool $isNested = false): void
    {
        if ($nestingDepth < 0) {
            throw new InvalidConfigurationException($config, 'Maximum nesting depth exceeded');
        }

        try {
            $properties = $config['properties'];
            $class = $config['class'];
            $schema = $config['schema'];

            if ($isNested) {
                if ($schema && !$this->dataSchemaService->isDataSchemaFileExists($schema)) {
                    throw new InvalidConfigurationException($config, "Nested property refers to nonexistent file \"{$schema}\"");
                }

                if (!$properties && !$schema) {
                    $message = 'Nested property should have "properties" or "schema" property to be defined';
                    throw new InvalidConfigurationException($config, $message);
                }
            } elseif (!$class || !$properties) {
                $message = 'Should has "class" and "properties" properties to be defined and not empty';
                throw new InvalidConfigurationException($config, $message);
            }

            try {
                $classMetadata = $this->getClassMetadata($config);
            } catch (\Exception $e) {
                throw new InvalidConfigurationException($config, $e->getMessage(), $e, $e);
            }

            if ($properties) {
                foreach ($properties as $propertyName => $propertyConfig) {
                    $source = $propertyConfig['source'] ?? null;
                    $decode = $propertyConfig['decode'] ?? null;
                    $isNestedProperty = $this->dataSchemaService->isNestedProperty($propertyConfig);
                    $isVirtualProperty = (bool) $source;
                    $hasDecodingFunction = (bool) $decode;

                    if ($isVirtualProperty) {
                        $this->validateVirtualProperty($config, $propertyName);
                    } else {
                        if ($classMetadata instanceof ClassMetadata) {
                            $this->validateClassProperty(
                                $classMetadata,
                                $propertyName,
                                $propertyConfig,
                                $isNestedProperty
                            );
                        }

                        if ($isNestedProperty) {
                            try {
                                $this->validate($propertyConfig, $nestingDepth - 1, true);
                            } catch (InvalidConfigurationException $e) {
                                throw new InvalidConfigurationPropertyException($propertyName, $e->getMessage(), $e, $e);
                            }
                        }
                    }

                    if ($hasDecodingFunction) {
                        $dataTransformerNames = $this->dataSchemaService->parseDecodeString($decode);

                        foreach ($dataTransformerNames as $dataTransformerName) {
                            try {
                                $this->dataSchemaService->getDataTransformer($dataTransformerName);
                            } catch (DataTransformerNotExists $e) {
                                throw new InvalidConfigurationPropertyException($propertyName, $e->getMessage(), $e, $e);
                            }
                        }
                    }
                }
            }
        } catch (InvalidConfigurationPropertyException|InvalidConfigurationException $e) {
            throw new InvalidConfigurationException($config, $e->getMessage(), $e, $e);
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfigurationPropertyException
     */
    private function validateClassProperty(ClassMetadata $classMetadata,
        string $name,
        array $config,
        bool $isNested): void
    {
        $class = $classMetadata->getName();
        $discriminator = $config['discriminator'] ?? null;
        $join = $config['join'] ?? null;

        if ($classMetadata->discriminatorColumn['name'] ?? null === $name) {
            return;
        }

        if (!$classMetadata->hasField($name) && !$classMetadata->hasAssociation($name)) {
            $discriminatorMap = $classMetadata->discriminatorMap;
            if (!$discriminatorMap) {
                $properties = $this->getAvailableProperties($classMetadata);

                $message = "Not found in class \"{$class}\". Available properties: ".json_encode($properties);
                throw new InvalidConfigurationPropertyException($name, $message);
            }

            if ($discriminator) {
                $subClass = $discriminatorMap[$discriminator] ?? null;

                if ($subClass) {
                    $subClassMetadata = $this->dataSchemaService->getClassMetadata($subClass);
                    if ($isNested && !$subClassMetadata->hasAssociation($name)) {
                        throw new InvalidConfigurationPropertyException($name, 'Nested property should have association mapping');
                    }

                    if (!$subClassMetadata->hasField($name) && !$subClassMetadata->hasAssociation($name)) {
                        $this->findPropertyAndThrowExceptionIfFound($subClass, $name, $discriminatorMap);

                        $message = "Class \"{$subClass}\" and all its siblings doesn't have this property";
                        throw new InvalidConfigurationPropertyException($name, $message);
                    }

                    if ($join && $join !== 'none') {
                        $message = "Subclass association can't be joined. You should use the \"none\" join";
                        throw new InvalidConfigurationPropertyException($name, $message);
                    }
                } else {
                    $discriminators = array_keys($discriminatorMap);
                    $message = "Invalid discriminator \"{$discriminator}\". Available discriminators: ".json_encode($discriminators);
                    throw new InvalidConfigurationPropertyException($name, $message);
                }
            } else {
                if ($isNested && !$classMetadata->hasAssociation($name)) {
                    throw new InvalidConfigurationPropertyException($name, 'Nested property should have association mapping');
                }

                $this->findPropertyAndThrowExceptionIfFound($class, $name, $discriminatorMap);

                $message = "Class \"{$class}\" and all its subclasses doesn't have this property";
                throw new InvalidConfigurationPropertyException($name, $message);
            }
        } elseif ($discriminator) {
            throw new InvalidConfigurationPropertyException($name, "Shouldn't have \"discriminator\" property defined");
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    private function validateVirtualProperty(array $config, string $name): void
    {
        $this->dataSchemaService->getPropertySourcesStack($config, $name);
    }

    /**
     * @throws InvalidConfigurationPropertyException
     */
    private function findPropertyAndThrowExceptionIfFound($class, string $name, array $discriminatorMap): void
    {
        foreach ($discriminatorMap as $discriminator => $mappedClass) {
            if ($class === $mappedClass) {
                continue;
            }

            $mappedClassMetadata = $this->dataSchemaService->getClassMetadata($mappedClass);

            if ($mappedClassMetadata->hasField($name) || $mappedClassMetadata->hasAssociation($name)) {
                $message = \sprintf(
                    'Class "%s" don\'t have this property, but "%s" has. You probably meant to use the "%s" discriminator',
                    $class,
                    $mappedClass,
                    $discriminator
                );
                throw new InvalidConfigurationPropertyException($name, $message);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function getClassMetadata(array $config): ?ClassMetadata
    {
        $class = $config['class'] ?? null;

        return $class ? $this->dataSchemaService->getClassMetadata($class) : null;
    }

    /**
     * @return string[]
     */
    private function getAvailableProperties(ClassMetadata $classMetadata): array
    {
        $allProperties = array_merge($classMetadata->getFieldNames(), $classMetadata->getAssociationNames());

        return array_map(
            static function (string $name) use ($classMetadata): string {
                if ($classMetadata->hasAssociation($name)) {
                    $type = $classMetadata->getAssociationTargetClass($name);
                } else {
                    $type = $classMetadata->getTypeOfField($name);
                }

                if ($classMetadata->isCollectionValuedAssociation($name)) {
                    $type .= '[]';
                }

                return "{$name}: {$type}";
            },
            $allProperties
        );
    }
}
