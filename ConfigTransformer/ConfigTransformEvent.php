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

class ConfigTransformEvent
{
    /**
     * @var array
     */
    private $path;

    /**
     * @var array
     */
    private $rootConfig;

    /**
     * @var array|null
     */
    private $scopeConfig;

    /**
     * @var string|null
     */
    private $queryLanguage;


    public function __construct(array $path, array $rootConfig, array $scopeConfig = null, string $queryLanguage = null)
    {

        $this->path = $path;
        $this->rootConfig = $rootConfig;
        $this->scopeConfig = $scopeConfig;
        $this->queryLanguage = $queryLanguage;
    }

    /**
     * @return array
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * @return array
     */
    public function getRootConfig(): array
    {
        return $this->rootConfig;
    }

    /**
     * @return array|null
     */
    public function getScopeConfig(): ?array
    {
        return $this->scopeConfig;
    }

    /**
     * @return string|null
     */
    public function getQueryLanguage(): ?string
    {
        return $this->queryLanguage;
    }
}