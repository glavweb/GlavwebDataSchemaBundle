<?php

namespace Glavweb\DataSchemaBundle\Exception\DataSchema;

use Glavweb\DataSchemaBundle\Exception\Exception;

class InvalidConfigurationPropertyException extends Exception
{
    /**
     * InvalidConfigurationPropertyException constructor.
     *
     * @param string $message
     * @param int    $code
     */
    public function __construct($propertyName, $message = '', $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Property \"{$propertyName}\": {$message}", $code, $previous);
    }
}
