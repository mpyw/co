<?php

namespace mpyw\Co\Internal;
use mpyw\Co\AllFailedException;
use mpyw\Co\CoInterface;

class ControlUtils
{
    /**
     * Executed by Co::any() or Co::race().
     * @param  mixed    $value
     * @param  callable $filter  self::reverse or self::fail.
     * @param  string   $message Used for failure.
     * @return \Generator
     */
    public static function anyOrRace($value, callable $filter, $message)
    {
        $value = YieldableUtils::normalize($value);
        $yieldables = YieldableUtils::getYieldables($value);
        $wrapper = self::getWrapperGenerator($yieldables, $filter);
        try {
            $results = (yield $wrapper);
        } catch (ControlException $e) {
            yield CoInterface::RETURN_WITH => $e->getValue();
        }
        $apply = YieldableUtils::getApplier($value, $yieldables);
        throw new AllFailedException($message, 0, $apply($results));
    }

    /**
     * Wrap yieldables with specified filter function.
     * @param  array    $yieldables
     * @param  callable $filter     self::reverse or self::fail.
     * @return \Generator
     */
    public static function getWrapperGenerator(array $yieldables, callable $filter)
    {
        $gens = [];
        foreach ($yieldables as $yieldable) {
            $gens[(string)$yieldable['value']] = $filter($yieldable['value']);
        }
        yield CoInterface::RETURN_WITH => (yield $gens);
        // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * Handle success as ControlException, failure as resolved.
     * @param  mixed      $yieldable
     * @return \Generator
     */
    public static function reverse($yieldable)
    {
        try {
            $result = (yield $yieldable);
        } catch (\RuntimeException $e) {
            yield CoInterface::RETURN_WITH => $e;
        }
        throw new ControlException($result);
    }

    /**
     * Handle success as ControlException.
     * @param  mixed      $yieldable
     * @return \Generator
     */
    public static function fail($yieldable)
    {
        throw new ControlException(yield $yieldable);
    }
}
