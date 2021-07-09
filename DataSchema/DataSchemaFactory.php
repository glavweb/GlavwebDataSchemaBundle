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
 * Class DataSchemaFactory
 *
 * @author  Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
class DataSchemaFactory
{

    /**
     * @var PersisterFactory
     */
    private $persisterFactory;

    /**
     * @var string
     */
    private $defaultHydratorMode;

    /**
     * @var Placeholder
     */
    private $placeholder;

    /**
     * @var ObjectHydrator
     */
    private $objectHydrator;

    /**
     * @var int
     */
    private $nestingDepth;

    /**
     * @var DataSchemaService
     */
    private $dataSchemaService;

    /**
     * @var DataSchemaFilter
     */
    private $dataSchemaFilter;

    /**
     * @var DataSchemaValidator
     */
    private $dataSchemaValidator;

    /**
     * DataSchema constructor.
     *
     * @param DataSchemaService   $dataSchemaService
     * @param DataSchemaFilter    $dataSchemaFilter
     * @param DataSchemaValidator $dataSchemaValidator
     * @param PersisterFactory    $persisterFactory
     * @param Placeholder         $placeholder
     * @param ObjectHydrator      $objectHydrator
     * @param int                 $nestingDepth
     * @param string|null         $defaultHydratorMode
     */
    public function __construct(DataSchemaService $dataSchemaService,
                                DataSchemaFilter $dataSchemaFilter,
                                DataSchemaValidator $dataSchemaValidator,
                                PersisterFactory $persisterFactory,
                                Placeholder $placeholder,
                                ObjectHydrator $objectHydrator,
                                int $nestingDepth,
                                string $defaultHydratorMode = null)
    {
        $this->dataSchemaService   = $dataSchemaService;
        $this->dataSchemaFilter    = $dataSchemaFilter;
        $this->dataSchemaValidator = $dataSchemaValidator;
        $this->persisterFactory    = $persisterFactory;
        $this->placeholder         = $placeholder;
        $this->objectHydrator      = $objectHydrator;
        $this->nestingDepth        = $nestingDepth;
        $this->defaultHydratorMode = $defaultHydratorMode;
    }

    /**
     * @param string      $dataSchemaFile
     * @param string|null $scopeFile
     * @return DataSchema
     * @throws InvalidConfigurationException|MappingException
     */
    public function createDataSchema(string $dataSchemaFile, string $scopeFile = null): DataSchema
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
            $this->nestingDepth,
            $this->defaultHydratorMode
        );
    }

    /**
     * @param string   $dataSchemaFile
     * @param array    $configuration
     * @param null     $scopeConfig
     * @param int|null $nestingDepth
     * @return DataSchema
     * @throws MappingException
     * @throws InvalidConfigurationException
     */
    public function createNestedDataSchema(string $dataSchemaFile,
                                           array $configuration,
                                           $scopeConfig = null,
                                           int $nestingDepth = null): DataSchema
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
            $nestingDepth,
            $this->defaultHydratorMode
        );
    }

}