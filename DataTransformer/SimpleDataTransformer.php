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
 * Class SimpleDataTransformer.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class SimpleDataTransformer implements DataTransformerInterface
{
    private $callable;

    /**
     * SimpleDataTransformer constructor.
     */
    public function __construct($callable)
    {
        if (!\is_callable($callable)) {
            throw new \RuntimeException('$callable argument must be callable.');
        }

        $this->callable = $callable;
    }

    public function transform($value, TransformEvent $transformEvent): mixed
    {
        return \call_user_func($this->callable, $value, $transformEvent);
    }
}
