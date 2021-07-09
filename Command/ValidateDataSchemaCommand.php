<?php

namespace Glavweb\DataSchemaBundle\Command;

use Glavweb\DataSchemaBundle\Service\DataSchemaValidator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ValidateDataSchemaCommand extends ContainerAwareCommand
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this->setName('glavweb:data-schema:validate')
             ->setDescription('Validates data schema configuration file')
             ->addArgument(
                 'path',
                 InputArgument::OPTIONAL,
                 'File path relative to directory defined in "data_schema.dir" bundle configuration parameter. Validates all files in folder if not specified.'
             );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var DataSchemaValidator $dataSchemaValidator */
        $dataSchemaValidator = $this->getContainer()->get('glavweb_data_schema.validator');
        $rootDir             = $this->getContainer()->getParameter('glavweb_data_schema.data_schema_dir');
        $nestingDepth        = $this->getContainer()->getParameter('glavweb_data_schema.data_schema_max_nesting_depth');

        $successful = false;
        $path       = $input->getArgument('path');

        $output->writeln(
            [
                'DataSchema Validator',
                '====================',
                '',
            ]
        );

        if ($path) {
            try {
                $dataSchemaValidator->validateFile($path, $nestingDepth);

                $successful = true;
                $output->writeln('<info>Validation successful</info>');

            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                $output->writeln('<error>Validation failed</error>');
            }

        } else {
            $finder = new Finder();
            $finder->in($rootDir)->files();
            $totalCount  = $finder->count();
            $errorsCount = 0;

            if (!$totalCount) {
                $output->writeln(sprintf('<comment>Files not found in "%s" directory</comment>', $rootDir));

                return 0;
            }

            $output->writeln([sprintf('<info>Validating %s configuration files...</info>', $totalCount), '']);

            foreach ($finder as $file) {
                try {
                    $dataSchemaValidator->validateFile($file->getRelativePathname(), $nestingDepth);
                } catch (\Exception $e) {
                    $errorsCount++;

                    $output->writeln(
                        [
                            sprintf('%s:', $file->getRelativePathname()),
                            '--------------------',
                            sprintf('<comment>%s</comment>', $e->getMessage()),
                            ''
                        ]
                    );
                }
            }

            if ($errorsCount) {
                $output->writeln(
                    sprintf('<error>Validation failed. %s configuration files have errors.</error>', $errorsCount)
                );

            } else {
                $successful = true;
                $output->writeln('<info>Validation successful</info>');
            }
        }

        return $successful ? 0 : 1;
    }
}