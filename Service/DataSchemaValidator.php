<?php

namespace Glavweb\DataSchemaBundle\Service;

use Doctrine\ORM\Mapping\ClassMetadata;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationException;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationPropertyException;
use Glavweb\DataSchemaBundle\Exception\DataTransformer\DataTransformerNotExists;

class DataSchemaValidator
{

    /**
     * @var DataSchemaService
     */
    private $dataSchemaService;

    /**
     * DataSchemaFilter constructor.
     */
    public function __construct(DataSchemaService $dataSchemaService)
    {
        $this->dataSchemaService = $dataSchemaService;
    }

    /**
     * @param string $dataSchemaFile
     * @param int    $nestingDepth
     * @throws InvalidConfigurationException
     */
    public function validateFile(string $dataSchemaFile, int $nestingDepth = 0): void
    {
        $configuration = $this->dataSchemaService->getConfigurationFromFile($dataSchemaFile);

        $this->validate($configuration, $nestingDepth);
    }

    /**
     * @param array $config
     * @param int   $nestingDepth
     * @param bool  $isNested
     * @throws InvalidConfigurationException
     */
    public function validate(array $config, int $nestingDepth = 0, bool $isNested = false): void
    {
        if ($nestingDepth < 0) {
            throw new InvalidConfigurationException($config, "Maximum nesting depth exceeded");
        }

        try {
            $properties = $config['properties'];
            $class      = $config['class'];
            $schema     = $config['schema'];

            if ($isNested) {
                if ($schema && !$this->dataSchemaService->isDataSchemaFileExists($schema)) {
                    throw new InvalidConfigurationException(
                        $config, "Nested property refers to nonexistent file \"$schema\""
                    );
                }

                if (!(($class && $properties) || $schema)) {
                    throw new InvalidConfigurationException(
                        $config,
                        "Nested property should have \"class\" and \"properties\" or \"schema\" property to be defined"
                    );
                }
            } else if (!$class || !$properties) {
                throw new InvalidConfigurationException(
                    $config, "Should has \"class\" and \"properties\" properties to be defined and not empty"
                );
            }

            try {
                $classMetadata = $this->getClassMetadata($config);
            } catch (\Exception $e) {
                throw new InvalidConfigurationException($config, $e->getMessage());
            }

            if ($properties) {

                foreach ($properties as $propertyName => $propertyConfig) {
                    $source              = $propertyConfig['source'] ?? null;
                    $decode              = $propertyConfig['decode'] ?? null;
                    $isNestedProperty    = $this->dataSchemaService->isNestedProperty($propertyConfig);
                    $isVirtualProperty   = (bool)$source;
                    $hasDecodingFunction = (bool)$decode;

                    if ($isVirtualProperty) {
                        $this->validateVirtualProperty($config, $propertyName);
                    } else {
                        if ($classMetadata) {
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
                                throw new InvalidConfigurationPropertyException($propertyName, $e->getMessage());
                            }
                        }
                    }

                    if ($hasDecodingFunction) {
                        $dataTransformerNames = $this->dataSchemaService->parseDecodeString($decode);

                        foreach ($dataTransformerNames as $dataTransformerName) {
                            try {
                                $this->dataSchemaService->getDataTransformer($dataTransformerName);
                            } catch (DataTransformerNotExists $e) {
                                throw new InvalidConfigurationPropertyException($propertyName, $e->getMessage());
                            }
                        }
                    }
                }

            }

        } catch (InvalidConfigurationPropertyException | InvalidConfigurationException $e) {
            throw new InvalidConfigurationException($config, $e->getMessage());
        }
    }

    /**
     * @param ClassMetadata $classMetadata
     * @param string        $name
     * @param array         $config
     * @param bool          $isNested
     * @return void
     * @throws InvalidConfigurationPropertyException
     */
    private function validateClassProperty(ClassMetadata $classMetadata,
                                           string $name,
                                           array $config,
                                           bool $isNested): void
    {
        $class         = $classMetadata->getName();
        $discriminator = $config['discriminator'] ?? null;
        $join          = $config['join'] ?? null;

        if (!$classMetadata->hasField($name) && !$classMetadata->hasAssociation($name)) {
            $discriminatorMap = $classMetadata->discriminatorMap;
            if (!$discriminatorMap) {
                $properties = $this->getAvailableProperties($classMetadata);

                throw new InvalidConfigurationPropertyException(
                    $name, "Not found in class \"$class\". Available properties: " . json_encode($properties)
                );
            }

            if ($discriminator) {
                $subClass = $discriminatorMap[$discriminator] ?? null;

                if ($subClass) {
                    $subClassMetadata = $this->dataSchemaService->getClassMetadata($subClass);
                    if ($isNested && !$subClassMetadata->hasAssociation($name)) {
                        throw new InvalidConfigurationPropertyException(
                            $name, "Nested property should have association mapping"
                        );
                    }

                    if (!$subClassMetadata->hasField($name) && !$subClassMetadata->hasAssociation($name)) {
                        $this->findPropertyAndThrowExceptionIfFound($subClass, $name, $discriminatorMap);

                        throw new InvalidConfigurationPropertyException(
                            $name, "Class \"$subClass\" and all its siblings doesn't have this property"
                        );
                    }

                    if ($join && $join !== 'none') {
                        throw new InvalidConfigurationPropertyException(
                            $name, "Subclass association can't be joined. You should use the \"none\" join"
                        );
                    }
                } else {
                    $discriminators = array_keys($discriminatorMap);
                    throw new InvalidConfigurationPropertyException(
                        $name, "Invalid discriminator \"$discriminator\". Available discriminators: " . json_encode(
                                 $discriminators
                             )
                    );
                }
            } else {
                if ($isNested && !$classMetadata->hasAssociation($name)) {
                    throw new InvalidConfigurationPropertyException(
                        $name, "Nested property should have association mapping"
                    );
                }

                $this->findPropertyAndThrowExceptionIfFound($class, $name, $discriminatorMap);

                throw new InvalidConfigurationPropertyException(
                    $name, "Class \"$class\" and all its subclasses doesn't have this property"
                );
            }
        } else if ($discriminator) {
            throw new InvalidConfigurationPropertyException(
                $name, "Shouldn't have \"discriminator\" property defined"
            );
        }

    }

    /**
     * @param array $config
     * @param       $name
     * @return void
     * @throws InvalidConfigurationException
     */
    private function validateVirtualProperty(array $config, $name): void
    {
        $this->dataSchemaService->getPropertySourcesStack($config, $name);
    }

    /**
     * @param        $class
     * @param string $name
     * @param array  $discriminatorMap
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
                throw new InvalidConfigurationPropertyException(
                    $name,
                    "Class \"$class\" don't have this property, but \"$mappedClass\" has. "
                    . "You probably meant to use the \"$discriminator\" discriminator"
                );
            }
        }
    }

    /**
     * @param array $config
     * @return ClassMetadata|null
     */
    private function getClassMetadata(array $config): ?ClassMetadata
    {
        $class = $config['class'] ?? null;

        return $class ? $this->dataSchemaService->getClassMetadata($class) : null;
    }

    /**
     * @param ClassMetadata $classMetadata
     * @return string[]
     */
    private function getAvailableProperties(ClassMetadata $classMetadata): array
    {
        $allProperties = array_merge($classMetadata->getFieldNames(), $classMetadata->getAssociationNames());

        return array_map(
            static function ($name) use ($classMetadata) {
                if ($classMetadata->hasAssociation($name)) {
                    $type = $classMetadata->getAssociationTargetClass($name);
                } else {
                    $type = $classMetadata->getTypeOfField($name);
                }

                if ($classMetadata->isCollectionValuedAssociation($name)) {
                    $type .= '[]';
                }

                return "$name: $type";
            },
            $allProperties
        );
    }
}