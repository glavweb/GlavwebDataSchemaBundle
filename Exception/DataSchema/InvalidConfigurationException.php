<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\Exception\DataSchema;

use Glavweb\DataSchemaBundle\Exception\Exception;
use Throwable;

/**
 * Class InvalidConfigurationException
 *
 * @package Glavweb\DataSchemaBundle
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class InvalidConfigurationException extends Exception
{
    /**
     * InvalidConfigurationException constructor.
     *
     * @param array|null     $configuration
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(array $configuration = null, $message = "", $code = 0, Throwable $previous = null)
    {
        $schemaName = $configuration['schema'] ?? null;

        if ($schemaName) {
            $message = "Schema \"$schemaName\": $message";
        }

        parent::__construct($message, $code, $previous);
    }
}