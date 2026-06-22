<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DependencyInjection\Compiler;

use Glavweb\DataSchemaBundle\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ExtensionPass.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class ExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ExtensionInterface::class)
            ->addTag('glavweb_data_schema.extension');
    }
}
