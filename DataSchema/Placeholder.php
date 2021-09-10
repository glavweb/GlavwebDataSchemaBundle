<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) GLAVWEB <info@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\DataSchema;

use Doctrine\Common\Annotations\Reader;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class Placeholder
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
class Placeholder
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var \Twig_Environment
     */
    private $twigEnvironment;

    /**
     * AccessHandler constructor.
     *
     * @param Reader $annotationReader
     * @param Security $security
     * @param SecurityExtension $securityExtension
     */
    public function __construct(Reader $annotationReader, Security $security, SecurityExtension $securityExtension)
    {
        $this->security = $security;

        $this->twigEnvironment = new \Twig\Environment(new \Twig\Loader\ArrayLoader([]), [
            'strict_variables' => true,
            'autoescape'       => false,
        ]);
        $this->twigEnvironment->addExtension($securityExtension);
    }

    /**
     * @param string $condition
     * @param string $alias
     * @param UserInterface $user
     * @return string
     */
    public function condition($condition, $alias, UserInterface $user = null)
    {
        if (!$user) {
            $user = $this->security->getUser();
        }

        $userId = null;
        if ($user instanceof UserInterface && method_exists($user, 'getId')) {
            $userId = $user->getId();
        }

        $template = $this->twigEnvironment->createTemplate($condition);

        return trim($template->render([
            'alias'  => $alias,
            'user'   => $user,
            'userId' => $userId,
        ]));
    }
}