<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\Loader\Yaml;

use Symfony\Component\Config\Exception\FileLoaderImportCircularReferenceException;
use Symfony\Component\Config\Exception\FileLoaderLoadException;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ScopeYamlLoader.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class ScopeYamlLoader extends FileLoader
{
    /**
     * @var mixed[]
     */
    private array $configuration = [];

    public function load(mixed $resource, $type = null): ?array
    {
        $path = $this->locator->locate($resource);
        $content = $this->loadFile($path);

        // empty file
        if (!$content) {
            return null;
        }

        // imports
        $this->parseImports($content, $path);

        $this->loadConfiguration($content);

        return $this->configuration;
    }

    /**
     * @param string $file
     */
    private function loadFile(string|array $file): mixed
    {
        return Yaml::parse(file_get_contents($file));
    }

    /**
     * @param array<string, mixed> $content
     *
     * @throws \Exception
     * @throws FileLoaderImportCircularReferenceException
     * @throws FileLoaderLoadException
     */
    private function parseImports(array $content, string|array $file): void
    {
        if (!isset($content['imports'])) {
            return;
        }

        if (!\is_array($content['imports'])) {
            $message = \sprintf('The "imports" key should contain an array in %s. Check your YAML syntax.', $file);
            throw new \InvalidArgumentException($message);
        }

        $defaultDirectory = \dirname($file);
        foreach ($content['imports'] as $import) {
            if (!\is_array($import)) {
                $message = \sprintf('The values in the "imports" key should be arrays in %s. Check your YAML syntax.', $file);
                throw new \InvalidArgumentException($message);
            }

            $this->setCurrentDir($defaultDirectory);
            $this->import($import['resource'], null, isset($import['ignore_errors']) && (bool) $import['ignore_errors'], $file);
        }
    }

    private function loadConfiguration(array $content): void
    {
        foreach ($content as $namespace => $values) {
            if ($namespace === 'imports') {
                continue;
            }

            if (!\is_array($values)) {
                $values = [];
            }

            $this->configuration = array_merge_recursive($this->configuration, $values);
        }
    }

    public function supports(mixed $resource, $type = null): bool
    {
        return \is_string($resource) && preg_match('/^ya?ml$/', pathinfo($resource, \PATHINFO_EXTENSION));
    }

    /**
     * @return mixed[]
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }
}
