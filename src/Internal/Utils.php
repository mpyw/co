<?php

namespace mpyw\Co\Utils;
use mpyw\Co\Co;

class Utils {

    /**
     * Normalize value.
     * @param mixed $value
     * @return miexed
     */
    public static function normalize($value)
    {
        while ($value instanceof \Closure) {
            $value = $value();
        }
        if (self::isArrayLike($value) && !is_array($value)) {
            $value = iterator_to_array($value);
        }
        return $value;
    }

    /**
     * Check if value is a valid cURL handle.
     * @param mixed $value
     * @return bool
     */
    public static function isCurl($value)
    {
        return is_resource($value) && get_resource_type($value) === 'curl';
    }

    /**
     * Check if value is a valid Generator.
     * @param mixed $value
     * @return bool
     */
    public static function isGenerator($value)
    {
        return $value instanceof \Generator;
    }

    /**
     * Check if value is a valid array or Traversable, not a Generator.
     * @param mixed $value
     * @return bool
     */
    public static function isArrayLike($value)
    {
        return $value instanceof \Traversable && !$value instanceof \Generator
               || is_array($value);
    }

    /**
     * Flatten an array or a Traversable.
     * @param mixed $value
     * @return array
     */
    public static function flatten($value, array &$carry = array())
    {
        if (!self::isArrayLike($value)) {
            $carry[] = $value;
        } else {
            foreach ($value as $v) {
                self::flatten($v, $carry);
            }
        }
        return func_num_args() <= 1 ? $carry : null;
    }

}
