<?php

namespace Glavweb\DataSchemaBundle\Exception\DataTransformer;

use Glavweb\DataSchemaBundle\Exception\Exception;

class DataTransformerNotExists extends Exception
{
    /**
     * DataTransformerNotExists constructor.
     *
     * @param int $code
     */
    public function __construct(string $name, $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("DataTransformer \"{$name}\" doesn't exist", $code, $previous);
    }
}
