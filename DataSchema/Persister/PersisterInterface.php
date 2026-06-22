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

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * Class PersisterInterface.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
interface PersisterInterface
{
    /**
     * @return array
     */
    public function getManyToManyData(array $associationMapping, $id, array $databaseFields, array $conditions = []);

    /**
     * @return array
     */
    public function getOneToManyData(array $associationMapping, $id, array $databaseFields, array $conditions = []);

    /**
     * @return array
     */
    public function getManyToOneData(array $associationMapping, $id, array $databaseFields, array $conditions = []);

    /**
     * @return array
     */
    public function getOneToOneData(array $associationMapping, $id, array $databaseFields, array $conditions = []);

    public function getPropertiesData(string $class, array $properties, int $id): array;

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getSelectQueryResult(string $class, string $selectClause, int $id);
}
