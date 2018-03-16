<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\Extension;

use Glavweb\DataSchemaBundle\DataTransformer\DataTransformerInterface;

/**
 * Class ExtensionInterface
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
interface ExtensionInterface
{
    /**
     * @return DataTransformerInterface[]
     */
    public function getDataTransformers();
}