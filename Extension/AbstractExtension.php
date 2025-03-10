<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\Extension;

/**
 * Class AbstractExtension
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class AbstractExtension implements ExtensionInterface
{

    /**
     * @inheritDoc
     */
    public function getDataTransformers()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getConfigTransformers()
    {
        return [];
    }
}