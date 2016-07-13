<?php

namespace mpyw\Co;
use mpyw\Co\Internal\GeneratorContainer;

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
        if ($value instanceof \Generator) {
            return new GeneratorContainer($value);
        }
        if (self::isArrayLike($value)) {
            $tmp = [];
            foreach ($value as $k => $v) {
                $tmp[$k] = self::normalize($value);
            }
            return $tmp;
        }
        return $value;
    }

    public static function getYieldables(array $array, array $keylist = [])
    {
        $r = [];
        foreach ($array as $key => $value) {
            array_splice($keylist, count($keylist), 0, $key);
            if (self::isCurl($value) || self::isGeneratorContainer($value)) {
                $r[] = [
                    'value' => $value,
                    'keylist' => $newlist,
                ];
            } elseif (is_array($value)) {
                array_splice($r, count($r), 0, self::getYieldables($value, $newlist));
            }
        }
        return $r;
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
    public static function isGeneratorContainer($value)
    {
        return $value instanceof GeneratorContainer;
    }

    /**
     * Check if value is a valid array or Traversable, not a Generator.
     * @param mixed $value
     * @return bool
     */
    public static function isArrayLike($value)
    {
        return
            $value instanceof \Traversable
            && !$value instanceof \Generator
            || is_array($value);
    }

}
