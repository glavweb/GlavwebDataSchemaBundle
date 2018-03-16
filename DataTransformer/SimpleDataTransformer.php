<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DataTransformer;

/**
 * Class SimpleDataTransformer
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
class SimpleDataTransformer implements DataTransformerInterface
{
    /**
     * @var mixed
     */
    private $callable;

    /**
     * SimpleDataTransformer constructor.
     *
     * @param mixed $callable
     */
    public function __construct($callable)
    {
        if (!is_callable($callable)) {
            throw new \RuntimeException('$callable argument must be callable.');
        }

        $this->callable = $callable;
    }

    /**
     * @param mixed          $value
     * @param TransformEvent $transformEvent
     * @return mixed
     */
    public function transform($value, TransformEvent $transformEvent)
    {
        return call_user_func($this->callable, $value, $transformEvent);
    }
}