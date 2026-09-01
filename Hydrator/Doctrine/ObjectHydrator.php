<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\Hydrator\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Doctrine\Persistence\ObjectManager;

/**
 * Class ObjectHydrator.
 *
 * The class based on https://github.com/pmill/doctrine-array-hydrator/blob/master/src/ArrayHydrator.php
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class ObjectHydrator
{
    /**
     * @var EntityManager
     */
    protected ObjectManager $entityManager;

    /**
     * @var bool
     */
    protected $hydrateAssociationReferences = true;

    public function __construct(Registry $doctrine)
    {
        $this->entityManager = $doctrine->getManager();
    }

    /**
     * @param object|string $entity
     *
     * @return object
     *
     * @throws \Exception
     */
    public function hydrate($entity, array $data, bool $hasAssociations = true)
    {
        if (\is_string($entity) && class_exists($entity)) {
            $entity = new $entity();
        } elseif (!\is_object($entity)) {
            throw new \Exception('Entity passed to ObjectHydrator::hydrate() must be a class name or entity object');
        }

        $entity = $this->hydrateProperties($entity, $data);

        if ($hasAssociations) {
            return $this->hydrateAssociations($entity, $data);
        }

        return $entity;
    }

    /**
     * @param bool $hydrateAssociationReferences
     */
    public function setHydrateAssociationReferences($hydrateAssociationReferences): void
    {
        $this->hydrateAssociationReferences = $hydrateAssociationReferences;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return object
     */
    protected function hydrateProperties($entity, array $data)
    {
        $reflectionClass = new \ReflectionClass($entity);
        $metaData = $this->entityManager->getClassMetadata($entity::class);

        foreach ($metaData->getFieldNames() as $propertyName) {
            if (isset($data[$propertyName])) {
                $entity = $this->setProperty($entity, $propertyName, $data[$propertyName], $reflectionClass);
            }
        }

        return $entity;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function hydrateAssociations($entity, array $data)
    {
        $metaData = $this->entityManager->getClassMetadata($entity::class);

        foreach ($metaData->getAssociationMappings() as $propertyName => $mapping) {
            if (isset($data[$propertyName])) {
                if ($mapping->isToOne()) {
                    $entity = $this->hydrateToOneAssociation($entity, $propertyName, $mapping, $data[$propertyName]);
                }

                if ($mapping->isToMany()) {
                    $entity = $this->hydrateToManyAssociation($entity, $propertyName, $mapping, $data[$propertyName]);
                }
            }
        }

        return $entity;
    }

    protected function hydrateToOneAssociation($entity, $propertyName, AssociationMapping $mapping, $value)
    {
        $reflectionClass = new \ReflectionClass($entity);

        if (\is_array($value)) {
            $metaData = $this->entityManager->getClassMetadata($mapping->targetEntity);
            $value = array_intersect_key($value, array_flip($metaData->getIdentifierColumnNames()));
        }

        $toOneAssociationObject = $this->fetchAssociationEntity($mapping->targetEntity, $value);

        if (null !== $toOneAssociationObject) {
            return $this->setProperty($entity, $propertyName, $toOneAssociationObject, $reflectionClass);
        }

        return $entity;
    }

    protected function hydrateToManyAssociation($entity, $propertyName, AssociationMapping $mapping, $value)
    {
        $reflectionClass = new \ReflectionClass($entity);
        $values = \is_array($value) ? $value : [$value];
        $associationObjects = [];

        foreach ($values as $value) {
            if (\is_array($value)) {
                $associationObjects[] = $this->hydrate($mapping->targetEntity, $value);
            } elseif ($associationObject = $this->fetchAssociationEntity($mapping->targetEntity, $value)) {
                $associationObjects[] = $associationObject;
            }
        }

        return $this->setProperty($entity, $propertyName, $associationObjects, $reflectionClass);
    }

    /**
     * @param object           $entity
     * @param string           $propertyName
     * @param \ReflectionClass $reflectionObject
     *
     * @throws \Exception
     */
    protected function setProperty($entity, $propertyName, $value, $reflectionObject = null)
    {
        $reflectionObject = $reflectionObject ?: new \ReflectionClass($entity);

        if (!$reflectionObject->hasProperty($propertyName)) {
            $parentReflectionClass = $reflectionObject->getParentClass();
            if (!$parentReflectionClass) {
                throw new \Exception(\sprintf('Property "%s" not found in class "%s".', $propertyName, $reflectionObject->getName()));
            }

            return $this->setProperty($entity, $propertyName, $value, $parentReflectionClass);
        }

        $property = $reflectionObject->getProperty($propertyName);
        $value = $this->coerceEnumValue($entity, $propertyName, $value, $reflectionObject);
        $value = $this->coerceDateTimeValue($entity, $propertyName, $value, $reflectionObject);
        $property->setValue($entity, $value);

        return $entity;
    }

    /**
     * Native SQL rows contain date/time columns as strings. Reflection assignment
     * bypasses entity setters, so convert them to DateTime / DateTimeImmutable.
     *
     * @param object                $entity
     * @param \ReflectionClass|null $reflectionObject
     */
    private function coerceDateTimeValue(object $entity, string $propertyName, mixed $value, $reflectionObject): mixed
    {
        if (!\is_string($value) || $value === '') {
            return $value;
        }

        $dateTimeClass = $this->resolveDateTimeClass($entity, $propertyName, $reflectionObject);
        if ($dateTimeClass === null) {
            return $value;
        }

        try {
            return new $dateTimeClass($value);
        } catch (\Exception) {
            return $value;
        }
    }

    /**
     * @param object                $entity
     * @param \ReflectionClass|null $reflectionObject
     *
     * @return class-string<\DateTimeInterface>|null
     */
    private function resolveDateTimeClass(object $entity, string $propertyName, $reflectionObject): ?string
    {
        $metaData = $this->entityManager->getClassMetadata($entity::class);
        if ($metaData->hasField($propertyName)) {
            $mapping = $metaData->getFieldMapping($propertyName);
            $typeName = \is_array($mapping) ? ($mapping['type'] ?? null) : ($mapping->type ?? null);
            if (\is_string($typeName)) {
                if (\str_ends_with($typeName, '_immutable') && $this->isDateTimeDoctrineType($typeName)) {
                    return \DateTimeImmutable::class;
                }
                if ($this->isDateTimeDoctrineType($typeName)) {
                    return \DateTime::class;
                }
            }
        }

        $reflectionObject = $reflectionObject ?: new \ReflectionClass($entity);
        if (!$reflectionObject->hasProperty($propertyName)) {
            $parent = $reflectionObject->getParentClass();

            return $parent ? $this->resolveDateTimeClass($entity, $propertyName, $parent) : null;
        }

        $type = $reflectionObject->getProperty($propertyName)->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $name = $type->getName();
            if (is_a($name, \DateTimeImmutable::class, true)) {
                return $name;
            }
            if (is_a($name, \DateTimeInterface::class, true)) {
                return $name === \DateTimeInterface::class ? \DateTime::class : $name;
            }
        }

        return null;
    }

    private function isDateTimeDoctrineType(string $typeName): bool
    {
        return \in_array($typeName, [
            'date',
            'datetime',
            'datetimetz',
            'time',
            'date_immutable',
            'datetime_immutable',
            'datetimetz_immutable',
            'time_immutable',
        ], true);
    }

    /**
     * Native SQL rows contain backed enum columns as scalars. Reflection assignment
     * bypasses entity setters, so convert string/int values to the property enum.
     *
     * @param object                $entity
     * @param \ReflectionClass|null $reflectionObject
     */
    private function coerceEnumValue(object $entity, string $propertyName, mixed $value, $reflectionObject): mixed
    {
        if (!\is_string($value) && !\is_int($value)) {
            return $value;
        }

        $enumType = $this->resolveEnumType($entity, $propertyName, $reflectionObject);
        if ($enumType === null) {
            return $value;
        }

        if (\is_string($value) && is_subclass_of($enumType, \BackedEnum::class)) {
            $backingType = (new \ReflectionEnum($enumType))->getBackingType()?->getName();
            if ($backingType === 'int' && is_numeric($value)) {
                return $enumType::from((int) $value);
            }
        }

        return $enumType::from($value);
    }

    /**
     * @param object                $entity
     * @param \ReflectionClass|null $reflectionObject
     */
    private function resolveEnumType(object $entity, string $propertyName, $reflectionObject): ?string
    {
        $metaData = $this->entityManager->getClassMetadata($entity::class);
        if ($metaData->hasField($propertyName)) {
            $mapping = $metaData->getFieldMapping($propertyName);
            $enumType = \is_array($mapping) ? ($mapping['enumType'] ?? null) : ($mapping->enumType ?? null);
            if (\is_string($enumType) && $enumType !== '' && is_subclass_of($enumType, \BackedEnum::class)) {
                return $enumType;
            }
        }

        $reflectionObject = $reflectionObject ?: new \ReflectionClass($entity);
        if (!$reflectionObject->hasProperty($propertyName)) {
            $parent = $reflectionObject->getParentClass();

            return $parent ? $this->resolveEnumType($entity, $propertyName, $parent) : null;
        }

        $type = $reflectionObject->getProperty($propertyName)->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $name = $type->getName();
            if (is_subclass_of($name, \BackedEnum::class)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param string $className
     *
     * @return bool|\Doctrine\Common\Proxy\Proxy|object|null
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected function fetchAssociationEntity($className, $id): ?object
    {
        if ($this->hydrateAssociationReferences) {
            return $this->entityManager->getReference($className, $id);
        }

        return $this->entityManager->find($className, $id);
    }
}
