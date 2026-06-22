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
    public function __construct(
        private readonly array $path,
        private readonly array $rootConfig,
        private readonly ?array $scopeConfig = null,
        private readonly ?string $queryLanguage = null,
    ) {
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function getRootConfig(): array
    {
        return $this->rootConfig;
    }

    public function getScopeConfig(): ?array
    {
        return $this->scopeConfig;
    }

    public function getQueryLanguage(): ?string
    {
        return $this->queryLanguage;
    }
}
