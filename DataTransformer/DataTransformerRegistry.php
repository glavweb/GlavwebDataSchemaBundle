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

use Glavweb\DataSchemaBundle\Extension\ExtensionInterface;

/**
 * Class DataTransformerRegistry
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
class DataTransformerRegistry
{
    /**
     * @var DataTransformerInterface[]
     */
    private $registry = [];

    /**
     * @param DataTransformerInterface $dataTransformer
     * @param string $name
     */
    public function add(DataTransformerInterface $dataTransformer, $name)
    {
        $this->registry[$name] = $dataTransformer;
    }

    /**
     * @param string $name
     * @return DataTransformerInterface
     */
    public function get($name)
    {
        return $this->registry[$name];
    }

    /**
     * @param string $name
     * @return DataTransformerInterface
     */
    public function has($name)
    {
        return isset($this->registry[$name]);
    }

    /**
     * @param ExtensionInterface $extension
     */
    public function loadExtension(ExtensionInterface $extension)
    {
        $dataTransformers = $extension->getDataTransformers();
        foreach ($dataTransformers as $name => $transformer) {
            $this->add($transformer, $name);
        }
    }
}