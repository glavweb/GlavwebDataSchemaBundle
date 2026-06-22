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

use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * Class Placeholder.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class Placeholder
{
    private readonly Environment $twigEnvironment;

    /**
     * AccessHandler constructor.
     */
    public function __construct(private readonly Security $security, SecurityExtension $securityExtension)
    {
        $this->twigEnvironment = new Environment(new ArrayLoader([]), [
            'strict_variables' => true,
            'autoescape' => false,
        ]);
        $this->twigEnvironment->addExtension($securityExtension);
    }

    /**
     * @throws LoaderError
     * @throws SyntaxError
     */
    public function condition(string $condition, string $alias, ?UserInterface $user = null): string
    {
        if (!$user instanceof UserInterface) {
            $user = $this->security->getUser();
        }

        $userId = null;
        if ($user instanceof UserInterface && method_exists($user, 'getId')) {
            $userId = $user->getId();
        }

        $template = $this->twigEnvironment->createTemplate($condition);

        return trim($template->render([
            'alias' => $alias,
            'user' => $user,
            'userId' => $userId,
        ]));
    }
}
