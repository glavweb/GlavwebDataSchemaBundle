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
 * Class DataTransformerRegistry.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class DataTransformerRegistry
{
    /**
     * @var DataTransformerInterface[]
     */
    private array $registry = [];

    /**
     * @param string $name
     */
    public function add(DataTransformerInterface $dataTransformer, $name): void
    {
        $this->registry[$name] = $dataTransformer;
    }

    /**
     * @param string $name
     *
     * @return DataTransformerInterface
     */
    public function get($name)
    {
        return $this->registry[$name];
    }

    /**
     * @param string $name
     */
    public function has($name): bool
    {
        return isset($this->registry[$name]);
    }

    public function loadExtension(ExtensionInterface $extension): void
    {
        $dataTransformers = $extension->getDataTransformers();
        foreach ($dataTransformers as $name => $transformer) {
            $this->add($transformer, $name);
        }
    }
}
