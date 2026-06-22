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
 * Class ScopeMaker.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 *
 * @method string getCommandDescription()
 */
class ScopeMaker extends AbstractMaker
{
    private const string ARGUMENT_MODEL_CLASS = 'model-class';

    private const string OPTION_EXAMPLE = 'example';

    public function __construct(
        private readonly Registry $doctrine,
        private readonly DoctrineHelper $doctrineHelper,
        private readonly string $scopeDir,
    ) {
    }

    #[\Override]
    public static function getCommandName(): string
    {
        return 'make:glavweb:scope';
    }

    #[\Override]
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument(
                self::ARGUMENT_MODEL_CLASS,
                InputArgument::REQUIRED,
                \sprintf('The class name of the entity to create scope (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm()))
            )
            ->addOption(self::OPTION_EXAMPLE, 'x', InputOption::VALUE_OPTIONAL, 'Also generate template')
            ->setDescription('Generates a scope based on the given entity class');

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

    #[\Override]
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument(self::ARGUMENT_MODEL_CLASS), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );

        $modelClass = $entityClassDetails->getFullName();
        $generateExample = $input->getOption(self::OPTION_EXAMPLE);
        $templateFilePath = $this->getTemplatePath($entityClassDetails);
        $filePath = $this->getFilePath($entityClassDetails);
        [$fields, $associations] = $this->getFieldsAndAssociations($modelClass);

        $templatePath = __DIR__.'/../Resources/skeleton/scope.yaml.tpl.php';

        if ($generateExample) {
            $generator->generateFile($templateFilePath, $templatePath, [
                'fields' => $fields,
                'associations' => $associations,
            ]);
        }

        if (!file_exists($filePath)) {
            $generator->generateFile($filePath, $templatePath, [
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
            $this->scopeDir,
            '_examples',
            Str::asFilePath($entityClassDetails->getRelativeNameWithoutSuffix()),
            Str::asFilePath($entityClassDetails->getShortName()).'.scope.yaml',
        ]);
    }

    private function getFilePath(ClassNameDetails $entityClassDetails): string
    {
        return implode('/', [
            $this->scopeDir,
            Str::asFilePath($entityClassDetails->getRelativeNameWithoutSuffix()),
            Str::asFilePath($entityClassDetails->getShortName()).'.scope.yaml',
        ]);
    }

    /**
     * @return array<int, mixed[]>
     *
     * @throws MappingException
     */
    private function getFieldsAndAssociations(string $modelClass): array
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

        $associations = [];
        $associationMappings = $metadata->getAssociationMappings();
        foreach ($associationMappings as $associationMapping) {
            $fieldName = $associationMapping['fieldName'];
            $associationModelClass = $associationMapping['targetEntity'];

            $associations[$fieldName] = $associationModelClass;
        }

        return [$fields, $associations];
    }
}
