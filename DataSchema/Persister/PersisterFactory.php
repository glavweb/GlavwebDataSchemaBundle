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
use Glavweb\DataSchemaBundle\DataSchema\DataSchema;

/**
 * Class PersisterFactory.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class PersisterFactory
{
    /**
     * @var Registry
     */
    public $doctrine;

    /**
     * Constants for db drivers.
     */
    public const DB_DRIVER_ORM = 'orm';

    /**
     * PersisterFactory constructor.
     */
    public function __construct(Registry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @return EntityPersister
     */
    public function createPersister(string $dbDriver, DataSchema $dataSchema)
    {
        return match ($dbDriver) {
            self::DB_DRIVER_ORM => new EntityPersister($this->doctrine, $dataSchema),
            default => throw new \RuntimeException(\sprintf('Db driver "%s" not support.', $dbDriver)),
        };
    }
}
