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
 * Interface DataTransformerInterface
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
interface DataTransformerInterface
{
    /**
     * @param mixed          $value
     * @param TransformEvent $transformEvent
     * @return mixed
     */
    public function transform($value, TransformEvent $transformEvent);
}