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
 * Class ConfigTransformerRegistry.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class ConfigTransformerRegistry
{
    /**
     * @var (int|ConfigTransformerInterface)[]
     */
    private array $registry = [];

    /**
     * @var ConfigTransformerInterface[]
     */
    private ?array $sortedTransformers = null;

    /**
     * @param string $name
     * @param int    $priority
     */
    public function add(ConfigTransformerInterface $configTransformer, $name, $priority = 0): void
    {
        $this->registry[$name] = [$priority, $configTransformer];
        $this->sortedTransformers = null;
    }

    /**
     * @param string $name
     *
     * @return ConfigTransformerInterface
     */
    public function get($name)
    {
        return $this->registry[$name][1];
    }

    /**
     * @return ConfigTransformerInterface[]
     */
    public function getAll(): array
    {
        if ($this->sortedTransformers !== null) {
            return $this->sortedTransformers;
        }

        $result = array_values($this->registry);
        usort($result, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $this->sortedTransformers = array_column($result, 1);

        return $this->sortedTransformers;
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
