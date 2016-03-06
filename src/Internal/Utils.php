<?php

namespace mpyw\Co\Utils;
use mpyw\Co\Co;

class Utils {

    /**
     * Validate options.
     *
     * @access private
     * @static
     * @param array $options
     * @return array
     */
    public static function validateOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (in_array($key, array('throw', 'pipeline', 'multiplex'), true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, array(
                    'flags' => FILTER_NULL_ON_FAILURE,
                ));
                if ($value === null) {
                    throw new \InvalidArgumentException("Option[$key] must be boolean.");
                }
            } elseif ($key === 'interval') {
                $value = filter_var($value, FILTER_VALIDATE_FLOAT);
                if ($value === false || $value < 0.0) {
                    throw new \InvalidArgumentException("Option[interval] must be positive float or zero.");
                }
            } elseif ($key === 'concurrency') {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false || $value < 0) {
                    throw new \InvalidArgumentException("Option[concurrency] must be positive integer or zero.");
                }
            } else {
                throw new \InvalidArgumentException("Unknown option: $key");
            }
            $options[$key] = $value;
        }
        return $options;
    }

    /**
     * Normalize value.
     *
     * @access private
     * @static
     * @param mixed $value
     * @return miexed
     */
    public static function normalize($value)
    {
        while ($value instanceof \Closure) {
            $value = $value();
        }
        if (self::isArrayLike($value)
            && !is_array($value)
            && !$value->valid()) {
            $value = array();
        }
        return $value;
    }

    /**
     * Check if value is a valid cURL resource.
     *
     * @access private
     * @static
     * @param mixed $value
     * @return bool
     */
    public static function isCurl($value)
    {
        return is_resource($value) && get_resource_type($value) === 'curl';
    }

    /**
     * Check if value is a valid Generator.
     *
     * @access private
     * @static
     * @param mixed $value
     * @return bool
     */
    public static function isGenerator($value)
    {
        return $value instanceof \Generator;
    }

    /**
     * Check if value is a valid array or Traversable, not a Generator.
     *
     * @access private
     * @static
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
     *
     * @access private
     * @static
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
