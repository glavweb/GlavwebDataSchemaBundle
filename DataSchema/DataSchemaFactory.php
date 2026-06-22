<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DataSchema;

use Doctrine\ORM\Mapping\MappingException;
use Glavweb\DataSchemaBundle\DataSchema\Persister\PersisterFactory;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationException;
use Glavweb\DataSchemaBundle\Hydrator\Doctrine\ObjectHydrator;
use Glavweb\DataSchemaBundle\Service\DataSchemaFilter;
use Glavweb\DataSchemaBundle\Service\DataSchemaService;
use Glavweb\DataSchemaBundle\Service\DataSchemaValidator;
use Glavweb\DataSchemaBundle\Util\Utils;

/**
 * Class DataSchemaFactory.
 *
 * @author  Andrey Nilov <nilov@glavweb.ru>
 */
class DataSchemaFactory
{
    /**
     * DataSchema constructor.
     */
    public function __construct(
        private readonly DataSchemaService $dataSchemaService,
        private readonly DataSchemaFilter $dataSchemaFilter,
        private readonly DataSchemaValidator $dataSchemaValidator,
        private readonly PersisterFactory $persisterFactory,
        private readonly Placeholder $placeholder,
        private readonly ObjectHydrator $objectHydrator,
        private readonly int $nestingDepth,
        private readonly ?string $defaultHydratorMode = null,
    ) {
    }

    /**
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    public function createDataSchema(string $dataSchemaFile, ?string $scopeFile = null, ?string $queryLanguage = null): DataSchema
    {
        $dataSchemaConfig = $this->dataSchemaService->getConfigurationFromFile($dataSchemaFile);

        $this->dataSchemaValidator->validate($dataSchemaConfig, $this->nestingDepth);

        $scopeConfig = null;
        if ($scopeFile) {
            $scopeConfig = $this->dataSchemaService->loadScopeConfiguration($scopeFile);
        }

        return new DataSchema(
            $this,
            $this->dataSchemaService,
            $this->dataSchemaFilter,
            $this->persisterFactory,
            $this->placeholder,
            $this->objectHydrator,
            $dataSchemaConfig,
            $scopeConfig,
            $queryLanguage,
            $this->nestingDepth,
            [],
            $this->defaultHydratorMode
        );
    }

    /**
     * @param int|null $nestingDepth
     *
     * @throws InvalidConfigurationException
     * @throws MappingException
     */
    public function createNestedDataSchema(string $dataSchemaFile,
        array $configuration,
        int $nestingDepth,
        array $path,
        ?array $scopeConfig = null,
        ?string $queryLanguage = null): DataSchema
    {
        $dataSchemaConfig = $this->dataSchemaService->getConfigurationFromFile($dataSchemaFile);

        $this->dataSchemaValidator->validate($dataSchemaConfig, $this->nestingDepth);

        $mergedConfig = Utils::arrayDeepMerge($dataSchemaConfig, $configuration);

        return new DataSchema(
            $this,
            $this->dataSchemaService,
            $this->dataSchemaFilter,
            $this->persisterFactory,
            $this->placeholder,
            $this->objectHydrator,
            $mergedConfig,
            $scopeConfig,
            $queryLanguage,
            $nestingDepth,
            $path,
            $this->defaultHydratorMode
        );
    }
}
