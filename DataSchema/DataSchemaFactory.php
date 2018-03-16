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

use Doctrine\Bundle\DoctrineBundle\Registry;
use Glavweb\DataSchemaBundle\DataSchema\Persister\PersisterFactory;
use Glavweb\DataSchemaBundle\DataTransformer\DataTransformerRegistry;
use Glavweb\DataSchemaBundle\Hydrator\Doctrine\ObjectHydrator;
use Glavweb\DataSchemaBundle\Loader\Yaml\DataSchemaYamlLoader;
use Glavweb\DataSchemaBundle\Loader\Yaml\ScopeYamlLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class DataSchemaFactory
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
class DataSchemaFactory
{
    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var DataTransformerRegistry
     */
    private $dataTransformerRegistry;

    /**
     * @var PersisterFactory
     */
    private $persisterFactory;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @var string
     */
    private $dataSchemaDir;

    /**
     * @var string
     */
    private $scopeDir;

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
     * DataSchema constructor.
     *
     * @param Registry                      $doctrine
     * @param DataTransformerRegistry       $dataTransformerRegistry
     * @param PersisterFactory              $persisterFactory
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param Placeholder                   $placeholder
     * @param ObjectHydrator                $objectHydrator
     * @param string                        $dataSchemaDir
     * @param string                        $scopeDir
     * @param string                        $defaultHydratorMode
     */
    public function __construct(
        Registry $doctrine,
        DataTransformerRegistry $dataTransformerRegistry,
        PersisterFactory $persisterFactory,
        AuthorizationCheckerInterface $authorizationChecker,
        Placeholder $placeholder,
        ObjectHydrator $objectHydrator,
        string $dataSchemaDir,
        string $scopeDir,
        $defaultHydratorMode = null
    ){
        $this->doctrine                = $doctrine;
        $this->dataTransformerRegistry = $dataTransformerRegistry;
        $this->persisterFactory        = $persisterFactory;
        $this->authorizationChecker    = $authorizationChecker;
        $this->placeholder             = $placeholder;
        $this->objectHydrator          = $objectHydrator;
        $this->dataSchemaDir           = $dataSchemaDir;
        $this->scopeDir                = $scopeDir;
        $this->defaultHydratorMode     = $defaultHydratorMode;
    }

    /**
     * @param string $dataSchemaFile
     * @param string $scopeFile
     * @param bool   $withoutInheritance
     * @return DataSchema
     */
    public function createDataSchema($dataSchemaFile, $scopeFile = null, $withoutInheritance = false)
    {
        $dataSchemaConfig = $this->getDataSchemaConfig($dataSchemaFile);

        $scopeConfig = null;
        if ($scopeFile) {
            $scopeConfig = $this->getScopeConfig($scopeFile);
        }

        return new DataSchema(
            $this,
            $this->doctrine,
            $this->dataTransformerRegistry,
            $this->persisterFactory,
            $this->authorizationChecker,
            $this->placeholder,
            $this->objectHydrator,
            $dataSchemaConfig,
            $scopeConfig,
            $withoutInheritance,
            $this->defaultHydratorMode
        );
    }

    /**
     * @param string $dataSchemaFile
     * @return array
     */
    public function getDataSchemaConfig($dataSchemaFile)
    {
        $dataSchemaLoader = new DataSchemaYamlLoader(new FileLocator($this->dataSchemaDir));
        $dataSchemaLoader->load($dataSchemaFile);

        return $dataSchemaLoader->getConfiguration();
    }

    /**
     * @param string $scopeFile
     * @return array
     */
    private function getScopeConfig($scopeFile)
    {
        $scopeLoader = new ScopeYamlLoader(new FileLocator($this->scopeDir));
        $scopeLoader->load($scopeFile);

        return $scopeLoader->getConfiguration();
    }
}