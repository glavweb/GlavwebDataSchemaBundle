<?php

/*
 * This file is part of the Glavweb DataSchemaBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\DataSchemaBundle\Maker;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

/**
 * Class DataSchemaMaker.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 *
 * @method string getCommandDescription()
 */
class DataSchemaMaker extends AbstractMaker
{
    private const string ARGUMENT_MODEL_CLASS = 'model-class';

    private const string EXAMPLE = 'example';

    public function __construct(
        private readonly Registry $doctrine,
        private readonly DoctrineHelper $doctrineHelper,
        private readonly string $dataSchemaDir,
    ) {
    }

    #[\Override]
    public static function getCommandName(): string
    {
        return 'make:glavweb:data-schema';
    }

    #[\Override]
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument(
                self::ARGUMENT_MODEL_CLASS,
                InputArgument::REQUIRED,
                \sprintf(
                    'The class name of the entity to create data schema (e.g. <fg=yellow>%s</>)',
                    Str::asClassName(Str::getRandomTerm())
                )
            )
            ->addOption(self::EXAMPLE, 'x', InputOption::VALUE_OPTIONAL, 'Also generate template')
            ->setDescription('Generates a data schema based on the given entity class');

        $inputConfig->setArgumentAsNonInteractive(self::ARGUMENT_MODEL_CLASS);
    }

    #[\Override]
    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    #[\Override]
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (null === $input->getArgument(self::ARGUMENT_MODEL_CLASS)) {
            $argument = $command->getDefinition()->getArgument(self::ARGUMENT_MODEL_CLASS);

            $entities = $this->doctrineHelper->getEntitiesForAutocomplete();

            $question = new Question($argument->getDescription());
            $question->setAutocompleterValues($entities);

            $value = $io->askQuestion($question);

            $input->setArgument(self::ARGUMENT_MODEL_CLASS, $value);
        }
    }

    /**
     * @throws MappingException
     */
    #[\Override]
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument(self::ARGUMENT_MODEL_CLASS), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );

        $modelClass = $entityClassDetails->getFullName();
        $generateExample = $input->getOption(self::EXAMPLE);
        $templateFilePath = $this->getTemplatePath($entityClassDetails);
        $filePath = $this->getFilePath($entityClassDetails);
        $fields = $this->getFields($modelClass);
        $associations = $this->getAssociations($modelClass);
        $templatePath = __DIR__.'/../Resources/skeleton/data_schema.yaml.tpl.php';

        if ($generateExample) {
            $generator->generateFile($templateFilePath, $templatePath, [
                'modelClass' => $modelClass,
                'fields' => $fields,
                'associations' => $associations,
            ]);
        }

        if (!file_exists($filePath)) {
            $generator->generateFile($filePath, $templatePath, [
                'modelClass' => $modelClass,
                'fields' => $fields,
                'associations' => $associations,
            ]);
        }

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
    }

    private function getTemplatePath(ClassNameDetails $entityClassDetails): string
    {
        return implode('/', [
            $this->dataSchemaDir,
            '_examples',
            Str::asFilePath($entityClassDetails->getRelativeNameWithoutSuffix()),
            Str::asFilePath($entityClassDetails->getShortName()).'.schema.yaml',
        ]);
    }

    private function getFilePath(ClassNameDetails $entityClassDetails): string
    {
        return implode('/', [
            $this->dataSchemaDir,
            Str::asFilePath($entityClassDetails->getRelativeNameWithoutSuffix()),
            Str::asFilePath($entityClassDetails->getShortName()).'.schema.yaml',
        ]);
    }

    /**
     * @throws MappingException
     */
    private function getFields(string $modelClass): array
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->doctrine->getManager()->getClassMetadata($modelClass);

        $fields = [];
        $fieldNames = $metadata->getFieldNames();
        foreach ($fieldNames as $fieldName) {
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            $type = $fieldMapping['type'];

            $fields[$fieldName] = $type;
        }

        return $fields;
    }

    /**
     * @throws MappingException
     */
    private function getAssociations(string $modelClass): array
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->doctrine->getManager()->getClassMetadata($modelClass);

        $associations = [];
        $associationMappings = $metadata->getAssociationMappings();
        foreach ($associationMappings as $associationMapping) {
            $fieldName = $associationMapping['fieldName'];
            $associationModelClass = $associationMapping['targetEntity'];
            $associationFields = $this->getFields($associationModelClass);

            $associations[$fieldName] = [
                'class' => $associationModelClass,
                'fields' => $associationFields,
            ];
        }

        return $associations;
    }
}
