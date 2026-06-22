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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds tagged glavweb_data_schema.data_transformer services to DataTransformerRegister service.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class DataTransformerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('glavweb_data_schema.data_transformer_registry')) {
            return;
        }

        // Data transformers
        $transformerRegistryDefinition = $container->getDefinition('glavweb_data_schema.data_transformer_registry');
        foreach ($container->findTaggedServiceIds('glavweb_data_schema.data_transformer') as $id => $tags) {
            if (!isset($tags[0]['transformer_name'])) {
                continue;
            }

            $transformerRegistryDefinition->addMethodCall('add', [new Reference($id), $tags[0]['transformer_name']]);
        }

        // Extensions
        foreach (array_keys($container->findTaggedServiceIds('glavweb_data_schema.extension')) as $id) {
            $transformerRegistryDefinition->addMethodCall('loadExtension', [new Reference($id)]);
        }
    }
}
