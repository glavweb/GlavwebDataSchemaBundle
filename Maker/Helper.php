<?php

declare(strict_types=1);

namespace Glavweb\DataSchemaBundle\Maker;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class Helper.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class Helper
{
    private string $entityNamespace;

    public function __construct(string $entityNamespace = 'App\\Entity\\')
    {
        $this->entityNamespace = rtrim($entityNamespace, '\\').'\\';
    }

    public function getEntityNamespace(): string
    {
        return $this->entityNamespace;
    }

    public function getDataSchemaName(string $modelClass): string
    {
        $relativeName = $this->relativeEntityName($modelClass);

        return Container::underscore(str_replace('\\', '/', $relativeName)).'.schema.yaml';
    }

    public function getScopeName(string $modelClass, string $scopeName): string
    {
        $relativeName = $this->relativeEntityName($modelClass);

        return Container::underscore(str_replace('\\', '/', $relativeName)).'/'.$scopeName.'.yaml';
    }

    public function relativeEntityName(string $modelClass): string
    {
        if (0 !== strpos($modelClass, $this->entityNamespace)) {
            throw new \RuntimeException(sprintf('Model class has prefix "%s"', rtrim($this->entityNamespace, '\\')));
        }

        return substr($modelClass, strlen($this->entityNamespace));
    }

    /**
     * @return mixed
     */
    public function lowerWords(string|array $spec): array|string
    {
        if (is_array($spec)) {
            foreach ($spec as $key => $value) {
                $spec[$key] = $this->lowerWords($value);
            }

            return $spec;
        }

        $spec = Inflector::tableize($spec);
        $spec = str_replace('_', ' ', $spec);

        return $spec;
    }

    /**
     * @return mixed
     */
    public function tableize(string|array $spec)
    {
        if (is_array($spec)) {
            foreach ($spec as $key => $value) {
                $spec[$key] = $this->tableize($value);
            }

            return $spec;
        }

        $spec = str_replace('\\', '', $spec);
        $spec = Inflector::tableize($spec);

        return $spec;
    }

    /**
     * @return mixed
     */
    public function plural(string|array $spec)
    {
        if (is_array($spec)) {
            foreach ($spec as $key => $value) {
                $spec[$key] = $this->plural($value);
            }

            return $spec;
        }

        $spec = Inflector::pluralize($spec);

        return $spec;
    }

    /**
     * @return mixed
     */
    public function lowerFirst(string|array $spec): array|string
    {
        if (is_array($spec)) {
            foreach ($spec as $key => $value) {
                $spec[$key] = $this->lowerFirst($value);
            }

            return $spec;
        }

        $spec = strtolower(substr($spec, 0, 1)).substr($spec, 1);

        return $spec;
    }

    /**
     * @return mixed
     */
    public function upperFirst(string|array $spec): array|string
    {
        if (is_array($spec)) {
            foreach ($spec as $key => $value) {
                $spec[$key] = $this->upperFirst($value);
            }

            return $spec;
        }

        $spec = ucfirst($spec);

        return $spec;
    }

    /**
     * @return mixed
     */
    public function lowerDash(string|array $spec): array|string
    {
        if (is_array($spec)) {
            foreach ($spec as $key => $value) {
                $spec[$key] = $this->lowerDash($value);
            }

            return $spec;
        }

        $spec = str_replace('_', '-', Inflector::tableize($spec));

        return $spec;
    }

    /**
     * @return mixed
     */
    public function singular(string|array $spec)
    {
        if (is_array($spec)) {
            foreach ($spec as $key => $value) {
                $spec[$key] = $this->singular($value);
            }

            return $spec;
        }

        $spec = Inflector::singularize($spec);

        return $spec;
    }

    /**
     * @return mixed
     */
    public function fixture(string $value)
    {
        if (!$value) {
            return '';
        }

        if (is_array($value)) {
            $count = count($value);
            $key = rand(0, $count - 1);

            return $value[$key];
        }

        $isDate = is_string($value) && preg_match('/\<\(new \\\DateTime\(\'(.*)\'\)\)\>/', $value, $matches);
        if ($isDate) {
            $value = $matches[1];
        }

        if (0 === strpos($value, '<')) {
            return '';
        }

        $value = str_replace("'", '"', $value);

        return $value;
    }

    /**
     * @return class-string<\BackedEnum>|null
     */
    public static function enumClassFromFieldMapping(array|object $fieldMapping): ?string
    {
        $enumType = null;
        if (is_array($fieldMapping) || $fieldMapping instanceof \ArrayAccess) {
            $enumType = $fieldMapping['enumType'] ?? null;
        } elseif (is_object($fieldMapping)) {
            $enumType = $fieldMapping->enumType ?? null;
        }

        return is_string($enumType) && enum_exists($enumType) ? $enumType : null;
    }

    public function modifyValue($value, $fieldType, $additional = 'new')
    {
        if ('boolean' == $fieldType) {
            $value = $value ? 'false' : 'true';
        } elseif (is_numeric($value)) {
            $value = $value + rand(1, 100);

        // is date
        } elseif ('date' == $fieldType || 'datetime' == $fieldType) {
            $date = new \DateTime($value);
            $value = $date->modify('+1 day')->format('Y-m-d');
        } elseif (\enum_exists($fieldType)) {
            $value = $this->getEnumValue($fieldType, $value);
        } else {
            $value = $additional.' '.$value;
        }

        return $value;
    }

    /**
     * @param class-string<\BackedEnum> $enumClass
     *
     * @return mixed
     */
    public function getEnumValue($enumClass, $value)
    {
        $values = $enumClass::getValues();

        // drop current value
        $currentKey = array_search($value, $values);
        if (false !== $currentKey) {
            unset($values[$currentKey]);
            $values = array_values($values);
        }

        $countValues = count($values);
        if ($countValues) {
            $numValue = rand(0, $countValues - 1);
            $value = $values[$numValue];
        }

        return $value;
    }

    public function isManyToMany(int $type): bool
    {
        return ClassMetadata::MANY_TO_MANY == $type;
    }

    public function isUploadableField(array $uploadableFields, string $fieldName): bool
    {
        foreach ($uploadableFields as $name => $uploadableField) {
            if ($name === $fieldName || ($uploadableField['fileNameProperty'] ?? null) === $fieldName) {
                return true;
            }
        }

        return false;
    }

    public function addBasenameSuffix($basename, $suffix): string
    {
        $basenameParts = $this->getCamelCaseParts($basename);
        $suffixParts = $this->getCamelCaseParts($suffix);

        $countSuffixParts = count($suffixParts);
        $countBasenameParts = count($basenameParts);
        for ($i = 0; $i < $countBasenameParts; ++$i) {
            $lastBasenamePartsKey = $countBasenameParts - $i - 1;
            $lastSuffixPartsKey = $countSuffixParts - $i - 1;

            if (isset($suffixParts[$lastSuffixPartsKey])
                && $basenameParts[$lastBasenamePartsKey] == $suffixParts[$lastSuffixPartsKey]
            ) {
                unset($basenameParts[$lastBasenamePartsKey]);
            }
        }

        $result = implode('', $basenameParts).$suffix;

        return $result;
    }

    /**
     * @param null $fieldType
     *
     * @return string
     */
    public function wrapInQuote($value, $fieldType = null, bool $singleQuotes = true)
    {
        if ('boolean' == $fieldType || 'integer' == $fieldType) {
            return $value;
        }

        if ($singleQuotes) {
            return "'".$value."'";
        }

        return '"'.$value.'"';
    }

    public function wrapInQuotes(array $array): array
    {
        return array_map(function ($item): string {
            return '"'.$item.'"';
        }, $array);
    }

    public function addToEndIfNotEmpty(string $string, string $part): string
    {
        if ($string) {
            return $string.$part;
        }

        return $string;
    }

    /**
     * @return mixed
     */
    private function getCamelCaseParts($suffix)
    {
        preg_match_all('/((?:^|[A-Z])[a-z]+)/', $suffix, $matches);

        return $matches[0];
    }

    public function capitalize(string $value): string
    {
        return ucfirst($value);
    }
}
