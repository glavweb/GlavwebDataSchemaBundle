<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DependencyInjection;

use Glavweb\DataSchemaBundle\ConfigTransformer\ConfigTransformerInterface;
use Glavweb\DataSchemaBundle\DataTransformer\DataTransformerInterface;
use Glavweb\DataSchemaBundle\Extension\ExtensionInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class GlavwebDataSchemaExtension.
 *
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class GlavwebDataSchemaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('glavweb_data_schema.default_hydrator_mode', $config['default_hydrator_mode']);
        $container->setParameter('glavweb_data_schema.data_schema_dir', $config['data_schema']['dir']);
        $container->setParameter('glavweb_data_schema.data_schema_max_nesting_depth', $config['data_schema']['max_nesting_depth']);
        $container->setParameter('glavweb_data_schema.scope_dir', $config['scope']['dir']);

        $container->registerForAutoconfiguration(ExtensionInterface::class)->addTag('glavweb_data_schema.extension');
        $container->registerForAutoconfiguration(DataTransformerInterface::class)->addTag('glavweb_data_schema.data_transformer');
        $container->registerForAutoconfiguration(ConfigTransformerInterface::class)->addTag('glavweb_data_schema.config_transformer');
    }
}
