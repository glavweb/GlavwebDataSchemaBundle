<?php

namespace Glavweb\DataSchemaBundle\Exception\ConfigTransformer;

use Glavweb\DataSchemaBundle\Exception\Exception;
use Throwable;

class ConfigTransformerNotExists extends Exception
{

    /**
     * ConfigTransformerNotExists constructor.
     *
     * @param string         $name
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $name, $code = 0, Throwable $previous = null)
    {
        parent::__construct("ConfigTransformer \"$name\" doesn't exist", $code, $previous);
    }
}