<?php

namespace Glavweb\DataSchemaBundle\Service;

use Doctrine\ORM\Mapping\ClassMetadata;
use Glavweb\DataSchemaBundle\Configuration\DataSchemaConfiguration;
use Glavweb\DataSchemaBundle\Exception\DataSchema\InvalidConfigurationException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DataSchemaFilter
{
    /**
     * DataSchemaFilter constructor.
     */
    public function __construct(
        private readonly DataSchemaService $dataSchemaService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public function filter(array $config, ?array $scopeConfig = null, int $nestingDepth = 0): array
    {
        if (!$this->isGranted($config['roles'] ?? [])) {
            return [];
        }

        $configProperties = $config['properties'];
        $result = $config + [];

        if ($configProperties) {
            $class = $config['class'] ?? null;
            $classMetadata = $class ? $this->dataSchemaService->getClassMetadata($class) : null;
            $identifierFieldNames =
                $classMetadata instanceof ClassMetadata ? $classMetadata->getIdentifierFieldNames() : [];

            $properties = [];

            foreach ($configProperties as $propertyName => $propertyConfig) {
                $propertyScopeConfig = $scopeConfig[$propertyName] ?? null;
                $isNested = $this->dataSchemaService->isNestedProperty($propertyConfig);
                $isIdentifier = \in_array($propertyName, $identifierFieldNames, true);
                $isHidden = $propertyConfig['hidden'] ?? false;
                $isInScope = $scopeConfig && \array_key_exists($propertyName, $scopeConfig);
                if (!$isInScope && !$isHidden && !$isIdentifier) {
                    continue;
                }

                if (!$isInScope && $isNested && $nestingDepth <= 0) {
                    continue;
                }

                $source = $propertyConfig['source'] ?? null;

                if ($source !== null && $source !== DataSchemaConfiguration::SOURCE_SELF_TOKEN) {
                    $propertySourcesStack = $this->dataSchemaService->getPropertySourcesStack($config, $propertyName);
                    foreach ($propertySourcesStack as [$sourcePropertyName, $sourcePropertyConfig]) {
                        if (!isset($properties[$sourcePropertyName]) && $sourcePropertyConfig) {
                            $sourcePropertyScopeConfig = $scopeConfig[$sourcePropertyName] ?? null;
                            $sourcePropertyConfig = $this->filterProperty(
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

    public function isGranted(array $roles): bool
    {
        if ($roles === []) {
            return true;
        }

        return array_any($roles, fn ($role): bool => $this->authorizationChecker->isGranted($role));
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function filterProperty(array $propertyConfig,
        ?array $scopeConfig,
        int $nestingDepth): array
    {
        $isNested = $this->dataSchemaService->isNestedProperty($propertyConfig);
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
