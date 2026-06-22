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

/**
 * Interface ConfigTransformerInterface.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
interface ConfigTransformerInterface
{
    public function transform(array $config, ConfigTransformEvent $transformEvent): array;
}
