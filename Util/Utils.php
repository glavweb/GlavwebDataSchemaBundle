<?php

namespace Glavweb\DataSchemaBundle\Util;

/**
 * Class Utils
 *
 * @package Glavweb\DataSchemaBundle\Util
 *
 * @author  Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class Utils
{
    public static function arrayDeepMerge(...$arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_int($key)) {
                    $result[] = $value;

                } elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                    $result[$key] = self::arrayDeepMerge($result[$key], $value);

                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}