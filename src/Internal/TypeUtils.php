<?php

namespace mpyw\Co\Internal;

class TypeUtils
{
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

    /**
     * Check if value is Throwable, excluding RuntimeException.
     * @param  mixed $value
     * @return bool
     */
    public static function isFatalThrowable($value)
    {
        return !$value instanceof \RuntimeException
            && ($value instanceof \Throwable || $value instanceof \Exception);
    }
}
