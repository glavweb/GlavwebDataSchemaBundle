<?php

namespace Glavweb\DataSchemaBundle\Exception\DataTransformer;

use Glavweb\DataSchemaBundle\Exception\Exception;
use Throwable;

class DataTransformerNotExists extends Exception
{

    /**
     * DataTransformerNotExists constructor.
     *
     * @param string         $name
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $name, $code = 0, Throwable $previous = null)
    {
        parent::__construct("DataTransformer \"$name\" doesn't exist", $code, $previous);
    }
}