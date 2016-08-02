<?php

namespace mpyw\Co\Internal;

use mpyw\Co\Internal\GeneratorContainer;

class Utils {

    /**
     * Recursively normalize value.
     *   Generator Closure  -> GeneratorContainer
     *   Array              -> Array (children's are normalized)
     *   Others             -> Others
     * @param  mixed    $value
     * @param  CoOption $options
     * @param  mixed    $yield_key
     * @return mixed
     */
    public static function normalize($value, CoOption $options, $yield_key = null)
    {
        if (self::isGeneratorClosure($value)) {
            $value = $value();
        }
        if ($value instanceof \Generator) {
            return new GeneratorContainer($value, $options, $yield_key);
        }
        if (is_array($value)) {
            $tmp = [];
            foreach ($value as $k => $v) {
                $tmp[$k] = self::normalize($v, $options, $yield_key);
            }
            return $tmp;
        }
        return $value;
    }

    /**
     * Recursively search yieldable values.
     * Each entries are assoc those contain keys 'value' and 'keylist'.
     *   value   -> the value itself.
     *   keylist -> position of the value. nests are represented as array values.
     * @param  mixed $value   Must be already normalized.
     * @param  array $keylist Internally used.
     * @return array
     */
    public static function getYieldables($value, array $keylist = [])
    {
        $r = [];
        if (!is_array($value)) {
            if (self::isCurl($value) || self::isGeneratorContainer($value)) {
                $r[(string)$value] = [
                    'value' => $value,
                    'keylist' => $keylist,
                ];
            }
            return $r;
        }
        foreach ($value as $k => $v) {
            $newlist = array_merge($keylist, [$k]);
            $r = array_merge($r, self::getYieldables($v, $newlist));
        }
        return $r;
    }

    /**
     * Check if value is a valid cURL handle.
     * @param  mixed $value
     * @return bool
     */
    public static function isCurl($value)
    {
        return is_resource($value) && get_resource_type($value) === 'curl';
    }

    /**
     * Check if value is a valid Generator.
     * @param  mixed $value
     * @return bool
     */
    public static function isGeneratorContainer($value)
    {
        return $value instanceof GeneratorContainer;
    }

    /**
     * Check if value is a valid Generator closure.
     * @param  mixed $value
     * @return bool
     */
    public static function isGeneratorClosure($value)
    {
        return $value instanceof \Closure
            && (new \ReflectionFunction($value))->isGenerator();
    }

}
