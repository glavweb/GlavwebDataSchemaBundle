<?php

namespace Glavweb\DataSchemaBundle\Service;

use Doctrine\ORM\Mapping\ClassMetadata;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DataSchemaFilter
{

    /**
     * @var DataSchemaService
     */
    private $dataSchemaService;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * DataSchemaFilter constructor.
     */
    public function __construct(DataSchemaService $dataSchemaService,
                                AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->dataSchemaService    = $dataSchemaService;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @param array      $config
     * @param array|null $scopeConfig
     * @param int        $nestingDepth
     * @return array
     * @throws InvalidConfigurationException
     */
    public function filter(array $config, array $scopeConfig = null, int $nestingDepth = 0): array
    {
        if (!$this->isGranted($config['roles'] ?? [])) {
            return [];
        }

        $configProperties = $config['properties'];
        $result           = $config + [];

        if ($configProperties) {
            $class                = $config['class'] ?? null;
            $classMetadata        = $class ? $this->dataSchemaService->getClassMetadata($class) : null;
            $identifierFieldNames =
                $classMetadata instanceof ClassMetadata ? $classMetadata->getIdentifierFieldNames() : [];

            $properties = [];

            foreach ($configProperties as $propertyName => $propertyConfig) {
                $propertyScopeConfig = $scopeConfig[$propertyName] ?? null;
                $isNested            = $propertyConfig['schema'] || $propertyConfig['properties'];
                $isIdentifier        = in_array($propertyName, $identifierFieldNames, true);
                $isHidden            = $propertyConfig['hidden'] ?? false;
                $isInScope           = array_key_exists($propertyName, $scopeConfig) || !$scopeConfig;

                if ((!$isInScope && !$isHidden && !$isIdentifier) || (!$isInScope && $isNested && $nestingDepth <= 0)) {
                    continue;
                }

                $source = $propertyConfig['source'] ?? null;

                if ($source) {
                    $propertySourcesStack = $this->dataSchemaService->getPropertySourcesStack($config, $propertyName);
                    foreach ($propertySourcesStack as [$sourcePropertyName, $sourcePropertyConfig]) {
                        if (!isset($properties[$sourcePropertyName]) && $sourcePropertyConfig) {
                            $sourcePropertyScopeConfig = $scopeConfig[$sourcePropertyName] ?? null;
                            $sourcePropertyConfig      = $this->filterProperty(
                                $sourcePropertyConfig,
                                $sourcePropertyScopeConfig,
                                $nestingDepth - 1
                            );

                            if ($sourcePropertyConfig) {
                                $properties[$sourcePropertyName] = $sourcePropertyConfig;
                            }
                        }
                    }
                }

                $propertyConfig = $this->filterProperty(
                    $propertyConfig,
                    $propertyScopeConfig,
                    $nestingDepth - 1
                );

                if ($propertyConfig) {
                    $properties[$propertyName] = $propertyConfig;
                }
            }

            $result['properties'] = $properties;
        }

        return $result;
    }

    /**
     * @param array $roles
     * @return bool
     */
    public function isGranted(array $roles): bool
    {
        if (empty($roles)) {
            return true;
        }

        foreach ($roles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array      $propertyConfig
     * @param array|null $scopeConfig
     * @param int        $nestingDepth
     * @return array|null
     * @throws InvalidConfigurationException
     */
    private function filterProperty(array $propertyConfig,
                                    ?array $scopeConfig,
                                    int $nestingDepth): ?array
    {
        $isNested                  = $propertyConfig['schema'] || $propertyConfig['properties'];
        $sourcePropertyScopeConfig = $scopeConfig ?? null;

        if ($isNested) {
            return $this->filter(
                $propertyConfig,
                $sourcePropertyScopeConfig,
                $nestingDepth
            );
        }

        return $propertyConfig;
    }
}