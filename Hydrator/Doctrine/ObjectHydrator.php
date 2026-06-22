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
use Doctrine\ORM\Mapping\ClassMetadataInfo;
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
                if (\in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE])) {
                    $entity = $this->hydrateToOneAssociation($entity, $propertyName, $mapping, $data[$propertyName]);
                }

                if (\in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY])) {
                    $entity = $this->hydrateToManyAssociation($entity, $propertyName, $mapping, $data[$propertyName]);
                }
            }
        }

        return $entity;
    }

    /**
     * @param array<string, mixed> $mapping
     */
    protected function hydrateToOneAssociation($entity, $propertyName, array $mapping, $value)
    {
        $reflectionClass = new \ReflectionClass($entity);

        if (\is_array($value)) {
            $metaData = $this->entityManager->getClassMetadata($mapping['targetEntity']);
            $value = array_intersect_key($value, array_flip($metaData->getIdentifierColumnNames()));
        }

        $toOneAssociationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value);

        if (null !== $toOneAssociationObject) {
            return $this->setProperty($entity, $propertyName, $toOneAssociationObject, $reflectionClass);
        }

        return $entity;
    }

    /**
     * @param array<string, mixed> $mapping
     */
    protected function hydrateToManyAssociation($entity, $propertyName, array $mapping, $value)
    {
        $reflectionClass = new \ReflectionClass($entity);
        $values = \is_array($value) ? $value : [$value];
        $associationObjects = [];

        foreach ($values as $value) {
            if (\is_array($value)) {
                $associationObjects[] = $this->hydrate($mapping['targetEntity'], $value);
            } elseif ($associationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value)) {
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
        $property->setValue($entity, $value);

        return $entity;
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
