<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\ConfigTransformer;

/**
 * Class SimpleConfigTransformer
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class SimpleConfigTransformer implements ConfigTransformerInterface
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
     * @param array $config
     * @param ConfigTransformEvent $transformEvent
     * @return array
     */
    public function transform(array $config, ConfigTransformEvent $transformEvent): array
    {
        return call_user_func($this->callable, $config, $transformEvent);
    }
}