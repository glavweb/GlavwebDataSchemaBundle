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

use Glavweb\DataSchemaBundle\Extension\ExtensionInterface;

/**
 * Class ConfigTransformerRegistry
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class ConfigTransformerRegistry
{
    /**
     * @var (int|ConfigTransformerInterface)[]
     */
    private $registry = [];

    /**
     * @var ConfigTransformerInterface[]
     */
    private $sortedTransformers;

    /**
     * @param ConfigTransformerInterface $configTransformer
     * @param string $name
     * @param int $priority
     */
    public function add(ConfigTransformerInterface $configTransformer, $name, $priority = 0)
    {
        $this->registry[$name] = [$priority, $configTransformer];
        $this->sortedTransformers = null;
    }

    /**
     * @param string $name
     * @return ConfigTransformerInterface
     */
    public function get($name)
    {
        return $this->registry[$name][1];
    }

    /**
     * @return ConfigTransformerInterface[]
     */
    public function getAll()
    {
        if ($this->sortedTransformers !== null) {
            return $this->sortedTransformers;
        }

        $result = \array_values($this->registry);
        \usort($result, function ($a, $b) {
            return $a[0] <=> $b[0];
        });


        $this->sortedTransformers = array_column($result, 1);

        return $this->sortedTransformers;
    }

    /**
     * @param string $name
     * @return bool
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
        $configTransformers = $extension->getConfigTransformers();
        foreach ($configTransformers as $name => $transformer) {
            $priority = 0;

            if (\is_array($transformer)) {
                $priority = $transformer[0];
                $transformer = $transformer[1];
            }

            $this->add($transformer, $name, $priority);
        }
    }
}