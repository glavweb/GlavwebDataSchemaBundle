<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DataSchema\Persister;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Glavweb\DataSchemaBundle\DataSchema\DataSchema;
use Glavweb\DataSchemaBundle\Exception\Persister\InvalidQueryException;

/**
 * Class EntityPersister.
 *
 * @author  Andrey Nilov <nilov@glavweb.ru>
 */
class EntityPersister implements PersisterInterface
{
    /**
     * EntityPersister constructor.
     *
     * @param int $hydrationMode
     */
    public function __construct(
        private readonly Registry $doctrine,
        private readonly DataSchema $dataSchema,
        private $hydrationMode = Query::HYDRATE_ARRAY,
    ) {
    }

    /**
     * @throws InvalidQueryException
     */
    public function getManyToManyData(
        array $associationMapping,
        $id,
        array $databaseFields,
        array $conditions = [],
        array $orderByExpressions = [],
    ): array {
        $query = $this->getQuery($associationMapping, $id, false, $databaseFields, $conditions, $orderByExpressions);

        return $query->getArrayResult();
    }

    /**
     * @param array<string, mixed> $associationMapping
     *
     * @throws InvalidQueryException
     */
    protected function getQuery(
        array $associationMapping,
        $id,
        bool $single,
        array $databaseFields,
        array $conditions = [],
        array $orderByExpressions = [],
    ): Query {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        $targetClass = $associationMapping['targetEntity'];
        $joinField = $associationMapping['isOwningSide'] ? $associationMapping['inversedBy'] : $associationMapping['mappedBy'];
        $targetAlias = uniqid('t', false);
        $sourceAlias = uniqid('s', false);
        $qb = $em->createQueryBuilder();

        if (!$joinField) {
            $sourceClass = $associationMapping['sourceEntity'];
            $sourceField = $associationMapping['fieldName'];
            $associationOperator = $single ? '=' : 'MEMBER OF';

            if (!$sourceField) {
                $message = \sprintf(
                    'The join filed part cannot be defined. May be you need configure association mapping for classes "%s" and "%s".',
                    $associationMapping['sourceEntity'],
                    $targetClass
                );
                throw new InvalidQueryException($message);
            }

            $qb
                ->select(\sprintf('PARTIAL %s.{%s}', $targetAlias, implode(',', $databaseFields)))
                ->from($targetClass, $targetAlias)
                ->join(
                    $sourceClass,
                    $sourceAlias,
                    Join::WITH,
                    \sprintf('%s %s %s.%s', $targetAlias, $associationOperator, $sourceAlias, $sourceField)
                )
                ->where($sourceAlias.'.id = :sourceId')
                ->setParameter('sourceId', $id);
        } else {
            $qb
                ->select(\sprintf('PARTIAL %s.{%s}', $targetAlias, implode(',', $databaseFields)))
                ->from($targetClass, $targetAlias)
                ->join(\sprintf('%s.%s', $targetAlias, $joinField), $sourceAlias)
                ->where($sourceAlias.'.id = :sourceId')
                ->setParameter('sourceId', $id);
        }

        foreach ($conditions as $conditionConfig) {
            if (!$conditionConfig['enabled']) {
                continue;
            }

            $preparedCondition = $this->dataSchema->conditionPlaceholder($conditionConfig['condition'], $targetAlias);
            if ($preparedCondition) {
                $qb->andWhere($preparedCondition);
            }
        }

        foreach ($orderByExpressions as $sort => $direction) {
            $qb->addOrderBy("{$targetAlias}.{$sort}", $direction);
        }

        return $qb->getQuery()->setHydrationMode($this->hydrationMode);
    }

    /**
     * @throws InvalidQueryException
     */
    public function getOneToManyData(
        array $associationMapping,
        $id,
        array $databaseFields,
        array $conditions = [],
        array $orderByExpressions = [],
    ): array {
        $query = $this->getQuery($associationMapping, $id, false, $databaseFields, $conditions, $orderByExpressions);

        return $query->getArrayResult();
    }

    /**
     * @throws InvalidQueryException
     * @throws NonUniqueResultException
     */
    public function getManyToOneData(array $associationMapping, $id, array $databaseFields, array $conditions = []): array
    {
        $query = $this->getQuery($associationMapping, $id, true, $databaseFields, $conditions);

        return (array) $query->getOneOrNullResult();
    }

    /**
     * @throws InvalidQueryException
     * @throws NonUniqueResultException
     */
    public function getOneToOneData(array $associationMapping, $id, array $databaseFields, array $conditions = []): array
    {
        $query = $this->getQuery($associationMapping, $id, true, $databaseFields, $conditions);

        return (array) $query->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getPropertiesData(string $class, array $properties, int $id): array
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $qb = $em->createQueryBuilder();
        $alias = 't';

        if (!\in_array('id', $properties, true)) {
            $properties[] = 'id';
        }

        $qb
            ->select(\sprintf('PARTIAL %s.{%s}', $alias, implode(',', $properties)))
            ->from($class, $alias)
            ->where($alias.'.id = :id')
            ->setParameter('id', $id);

        $query = $qb->getQuery();

        return (array) $query->getSingleResult($this->hydrationMode);
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getSelectQueryResult(string $class, string $selectClause, int $id): mixed
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $qb = $em->createQueryBuilder();
        $alias = 't';

        $qb
            ->select(\sprintf('(%s)', $selectClause))
            ->from($class, $alias)
            ->where($alias.'.id = :id')
            ->setParameter('id', $id);

        $query = $qb->getQuery();

        return $query->getSingleScalarResult();
    }
}
