<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Glavweb\DataSchemaBundle\Generator\DataSchemaGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class GenerateDataSchemaCommand
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 * @package Glavweb\DataSchemaBundle
 */
class GenerateDataSchemaCommand extends Command
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var string
     */
    private $dataSchemaDir;

    public function __construct(ManagerRegistry $doctrine, KernelInterface $kernel, string $dataSchemaDir)
    {
        $this->doctrine = $doctrine;
        $this->kernel = $kernel;
        $this->dataSchemaDir = $dataSchemaDir;

        parent::__construct();
    }

    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('glavweb:data-schema')
            ->setDescription('Generates a data schema based on the given model class')
            ->addArgument('model', InputArgument::REQUIRED, 'The fully qualified model class')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $modelClass        = $this->validateClass($input->getArgument('model'));
        $skeletonDirectory = __DIR__ . '/../Resources/skeleton';

        try {
            $dataSchemaGenerator = new DataSchemaGenerator(
                $this->kernel,
                $this->doctrine,
                $this->dataSchemaDir,
                $skeletonDirectory
            );

            $dataSchemaGenerator->generate($modelClass);
            $output->writeln(sprintf(
                '%sThe example for data schema file "<info>%s</info>" has been %s.',
                PHP_EOL,
                realpath($dataSchemaGenerator->getTemplateFile()),
                ($dataSchemaGenerator->isFileTemplateUpdated() ? 'updated' : 'generated')
            ));

            if ($dataSchemaGenerator->isFileGenerated()) {
                $output->writeln(sprintf(
                    '%sThe data schema file "<info>%s</info>" has been generated.',
                    PHP_EOL,
                    realpath($dataSchemaGenerator->getFile())
                ));
            }

        } catch (\Exception $e) {
            $this->writeError($output, $e->getMessage());
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param string $message
     */
    protected function writeError(OutputInterface $output, $message)
    {
        $output->writeln(sprintf("\n<error>%s</error>", $message));
    }

    /**
     *
     * @param string $class
     * @return string
     * @throws \InvalidArgumentException
     */
    public function validateClass($class)
    {
        $class = str_replace('/', '\\', $class);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('The class "%s" does not exist.', $class));
        }

        return $class;
    }
}
