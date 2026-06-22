<?php

namespace Glavweb\DataSchemaBundle\Exception\ConfigTransformer;

use Glavweb\DataSchemaBundle\Exception\Exception;

class ConfigTransformerNotExists extends Exception
{
    /**
     * ConfigTransformerNotExists constructor.
     *
     * @param int $code
     */
    public function __construct(string $name, $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("ConfigTransformer \"{$name}\" doesn't exist", $code, $previous);
    }
}
