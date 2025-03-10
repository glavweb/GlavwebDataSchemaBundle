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

use Glavweb\DataSchemaBundle\ConfigTransformer\ConfigTransformerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged glavweb_data_schema.config_transformer services to ConfigTransformerRegister service.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class ConfigTransformerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('glavweb_data_schema.config_transformer_registry')) {
            return;
        }

        // Data transformers
        $transformerRegistryDefinition = $container->getDefinition('glavweb_data_schema.config_transformer_registry');
        foreach ($container->findTaggedServiceIds('glavweb_data_schema.config_transformer') as $id => $tags) {
            $name = $tags[0]['transformer_name'] ?? $id;

            $transformerRegistryDefinition->addMethodCall('add', [new Reference($id), $name]);
        }
        
        // Extensions
        foreach ($container->findTaggedServiceIds('glavweb_data_schema.extension') as $id => $tags) {
            $transformerRegistryDefinition->addMethodCall('loadExtension', [new Reference($id)]);
        }
    }
}
