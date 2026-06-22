<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DataTransformer;

use Glavweb\DataSchemaBundle\DataSchema\DataSchemaFactory;
use Glavweb\DataSchemaBundle\Hydrator\Doctrine\ObjectHydrator;

/**
 * Class TransformEvent.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class TransformEvent
{
    /**
     * TransformEvent constructor.
     *
     * @param string $className
     * @param string $propertyName
     * @param string $parentClassName
     * @param string $parentPropertyName
     */
    public function __construct(
        private $className,
        private $propertyName,
        private readonly array $propertyConfig,
        private $parentClassName,
        private $parentPropertyName,
        private readonly array $data,
        private readonly ObjectHydrator $objectHydrator,
        private readonly DataSchemaFactory $dataSchemaFactory,
    ) {
    }

    public function getPropertyName()
    {
        return $this->propertyName;
    }

    public function getPropertyConfig(): array
    {
        return $this->propertyConfig;
    }

    /**
     * @return string
     */
    public function getParentClassName()
    {
        return $this->parentClassName;
    }

    /**
     * @return string
     */
    public function getParentPropertyName()
    {
        return $this->parentPropertyName;
    }

    /**
     * @return object
     */
    public function getEntity(bool $hasAssociations = true)
    {
        return $this->objectHydrator->hydrate($this->getClassName(), $this->getData(), $hasAssociations);
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDataSchemaFactory(): DataSchemaFactory
    {
        return $this->dataSchemaFactory;
    }
}
