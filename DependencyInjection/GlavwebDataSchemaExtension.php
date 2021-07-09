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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class GlavwebDataSchemaExtension
 *
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 *
 * @package Glavweb\DataSchemaBundle
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class GlavwebDataSchemaExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('glavweb_data_schema.default_hydrator_mode', $config['default_hydrator_mode']);
        $container->setParameter('glavweb_data_schema.data_schema_dir', $config['data_schema']['dir']);
        $container->setParameter('glavweb_data_schema.data_schema_max_nesting_depth', $config['data_schema']['max_nesting_depth']);
        $container->setParameter('glavweb_data_schema.scope_dir', $config['scope']['dir']);
    }
}
