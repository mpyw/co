<?php

namespace mpyw\Co\Internal;
use React\Promise\PromiseInterface;

class YieldableUtils
{
    /**
     * Recursively normalize value.
     *   Generator Closure  -> GeneratorContainer
     *   Array              -> Array (children's are normalized)
     *   Others             -> Others
     * @param  mixed    $value
     * @param  mixed    $yield_key
     * @return mixed
     */
    public static function normalize($value, $yield_key = null)
    {
        if (TypeUtils::isGeneratorClosure($value)) {
            $value = $value();
        }
        if ($value instanceof \Generator) {
            return new GeneratorContainer($value, $yield_key);
        }
        if (is_array($value)) {
            $tmp = [];
            foreach ($value as $k => $v) {
                $tmp[$k] = self::normalize($v, $yield_key);
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
     * @param  array &$runners Running cURL or Generator identifiers.
     * @return array
     */
    public static function getYieldables($value, array $keylist = [], array &$runners = [])
    {
        $r = [];
        if (!is_array($value)) {
            if (TypeUtils::isCurl($value) || TypeUtils::isGeneratorContainer($value)) {
                if (isset($runners[(string)$value])) {
                    throw new \DomainException('Duplicated cURL resource or Generator instance found.');
                }
                $r[(string)$value] = $runners[(string)$value] = [
                    'value' => $value,
                    'keylist' => $keylist,
                ];
            }
            return $r;
        }
        foreach ($value as $k => $v) {
            $newlist = array_merge($keylist, [$k]);
            $r = array_merge($r, self::getYieldables($v, $newlist, $runners));
        }
        return $r;
    }

    /**
     * Return function that apply changes in yieldables.
     * @param  mixed         $yielded
     * @param  array         $yieldables
     * @param  callable|null $next
     * @return mixed
     */
    public static function getApplier($yielded, array $yieldables, callable $next = null)
    {
        return function (array $results) use ($yielded, $yieldables, $next) {
            foreach ($results as $hash => $resolved) {
                $current = &$yielded;
                foreach ($yieldables[$hash]['keylist'] as $key) {
                    $current = &$current[$key];
                }
                $current = $resolved;
                unset($current);
            }
            return $next ? $next($yielded) : $yielded;
        };
    }

    /**
     * Return Promise that absorbs rejects, excluding fatal Throwable.
     * @param  PromiseInterface $promise
     * @return PromiseInterface
     */
    public static function safePromise(PromiseInterface $promise)
    {
        return $promise->then(null, function ($value) {
            if (TypeUtils::isFatalThrowable($value)) {
                throw $value;
            }
            return $value;
        });
    }
}
