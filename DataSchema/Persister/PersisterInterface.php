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

/**
 * Class PersisterInterface
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
interface PersisterInterface
{
    /**
     * @param array $associationMapping
     * @param mixed $id
     * @param array $databaseFields
     * @param array $conditions
     * @return array
     */
    public function getManyToManyData(array $associationMapping, $id, array $databaseFields, array $conditions = []);

    /**
     * @param array $associationMapping
     * @param mixed $id
     * @param array $databaseFields
     * @param array $conditions
     * @return array
     */
    public function getOneToManyData(array $associationMapping, $id, array $databaseFields, array $conditions = []);

    /**
     * @param array $associationMapping
     * @param mixed $id
     * @param array $databaseFields
     * @param array $conditions
     * @return array
     */
    public function getManyToOneData(array $associationMapping, $id, array $databaseFields, array $conditions = []);

    /**
     * @param array $associationMapping
     * @param mixed $id
     * @param array $databaseFields
     * @param array $conditions
     * @return array
     */
    public function getOneToOneData(array $associationMapping, $id, array $databaseFields, array $conditions = []);
}
