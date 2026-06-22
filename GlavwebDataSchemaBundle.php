<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle;

use Glavweb\DataSchemaBundle\DependencyInjection\Compiler\ConfigTransformerPass;
use Glavweb\DataSchemaBundle\DependencyInjection\Compiler\DataTransformerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class GlavwebDataSchemaBundle.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class GlavwebDataSchemaBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DataTransformerPass());
        $container->addCompilerPass(new ConfigTransformerPass());
    }
}
