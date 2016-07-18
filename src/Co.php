<?php

namespace mpyw\Co;
use mpyw\Co\Internal\Utils;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Co\Internal\CURLPool;

use mpyw\RuntimePromise\Deferred;
use mpyw\RuntimePromise\PromiseInterface;

class Co implements CoInterface
{
    /**
     * Instance of myself.
     * @var Co
     */
    private static $self;

    /**
     * Options.
     * @var CoOption
     */
    private $options;

    /**
     * cURL request pool object.
     * @var CURLPool
     */
    private $pool;

    /**
     * Overwrite CoOption default.
     * @param array $options
     */
    public static function setDefaultOptions(array $options)
    {
        CoOption::setDefault($options);
    }

    /**
     * Get CoOption default as array.
     * @return array
     */
    public static function getDefaultOptions()
    {
        return CoOption::getDefault();
    }

    /**
     * Wait until value is recursively resolved to return it.
     * This function call must be atomic.
     * @param  mixed $value
     * @param  array $options
     * @return mixed
     * @codeCoverageIgnore
     */
    public static function wait($value, array $options = [])
    {
        // Coverage analyzer does not support...
        //   try { return; } finally { }
        try {
            if (self::$self) {
                throw new \BadMethodCallException('Co::wait() is already running. Use Co::async() instead.');
            }
            self::$self = new self;
            self::$self->options = new CoOption($options);
            self::$self->pool = new CURLPool(self::$self->options);
            return self::$self->start($value);
        } finally {
            self::$self = null;
        }
    }

    /**
     * Value is recursively resolved, but we never wait it.
     * This function must be called along with Co::wait().
     * @param  mixed $value
     * @param  array $options
     */
    public static function async($value, array $options = [])
    {
        if (!self::$self) {
            throw new \BadMethodCallException(
                'Co::async() must be called along with Co::wait(). ' .
                'This method is mainly expected to be used in CURLOPT_WRITEFUNCTION callback.'
            );
        }
        self::$self->start($value, false);
    }

    /**
     * External instantiation is forbidden.
     */
    private function __construct() {}

    /**
     * Start resovling.
     * @param  mixed    $value
     * @param  bool     $wait
     * @param  mixed    If $wait, return resolved value.
     */
    private function start($value, $wait = true)
    {
        $return = $exception = null;
        $deferred = new Deferred;
        $deferred->promise()->then(
            function ($r) use (&$return) {
                $return = $r;
            },
            function ($e) use (&$exception) {
                $exception = $e;
            }
        );
        // For convenience, all values are wrapped into generator
        $genfunc = function () use ($value) {
            yield CoInterface::RETURN_WITH => (yield $value);
        };
        $con = Utils::normalize($genfunc, $this->options);
        // We have to provide deferred object only if $wait
        $this->processGeneratorContainer($con, $deferred);
        // We have to wait $return only if $wait
        if ($wait) {
            $this->pool->wait();
            if ($exception) {
                throw $exception;
            }
            return $return;
        }
    }

    /**
     * Handle resolving generators.
     * @param  GeneratorContainer $gc
     * @param  Deferred           $deferred
     */
    private function processGeneratorContainer(GeneratorContainer $gc, Deferred $deferred)
    {
        // If generator has no more yields...
        if (!$gc->valid()) {
            // If exception has been thrown in generator, we have to propagate it as rejected value
            if ($gc->thrown()) {
                $deferred->reject($gc->getReturnOrThrown());
                return;
            }
            // Now we normalize returned value
            try {
                $returned = Utils::normalize($gc->getReturnOrThrown(), $gc->getOptions());
                $yieldables = Utils::getYieldables($returned);
                // If normalized value contains yieldables, we have to chain resolver
                if ($yieldables) {
                    $this->promiseAll($yieldables, true)->then(
                        self::getApplier($returned, $yieldables, [$deferred, 'resolve']),
                        [$deferred, 'reject']
                    );
                    return;
                }
                // Propagate normalized returned value
                $deferred->resolve($returned);
            } catch (\RuntimeException $e) {
                // Propagate exception thrown in normalization
                $deferred->reject($e);
            }
            return;
        }

        // Now we normalize yielded value
        try {
            $yielded = Utils::normalize($gc->current(), $gc->getOptions(), $gc->key());
        } catch (\RuntimeException $e) {
            // If exception thrown in normalization...
            //   - If generator accepts exception, we throw it into generator
            //   - If generator does not accept exception, we assume it as non-exception value
            $gc->throwAcceptable() ? $gc->throw_($e) : $gc->send($e);
            // Continue
            $this->processGeneratorContainer($gc, $deferred);
            return;
        }

        // Search yieldables from yielded value
        $yieldables = Utils::getYieldables($yielded);
        if (!$yieldables) {
            // If there are no yieldables, send yielded value back into generator
            $gc->send($yielded);
            // Continue
            $this->processGeneratorContainer($gc, $deferred);
            return;
        }

        // Chain resolver
        $this->promiseAll($yieldables, $gc->throwAcceptable())->then(
            self::getApplier($yielded, $yieldables, [$gc, 'send']),
            [$gc, 'throw_']
        )->always(function () use ($gc, $deferred) {
            // Continue
            $this->processGeneratorContainer($gc, $deferred);
        });
    }

    /**
     * Return function that apply changes in yieldables.
     * @param  mixed    $yielded
     * @param  array    $yieldables
     * @param  callable $next
     */
    private static function getApplier($yielded, $yieldables, callable $next)
    {
        return function (array $results) use ($yielded, $yieldables, $next) {
            foreach ($results as $hash => $resolved) {
                $current = &$yielded;
                foreach ($yieldables[$hash]['keylist'] as $key) {
                    $current = &$current[$key];
                }
                $current = $resolved;
            }
            $next($yielded);
        };
    }

    /**
     * Promise all changes in yieldables are prepared.
     * @param  array $yieldables
     * @param  bool  $throw_acceptable
     * @return PromiseInterface
     */
    private function promiseAll($yieldables, $throw_acceptable)
    {
        $promises = [];
        foreach ($yieldables as $yieldable) {
            $dfd = new Deferred;
            $promises[(string)$yieldable['value']] = $dfd->promise();
            // If caller cannot accept exception,
            // we handle rejected value as resolved.
            if (!$throw_acceptable) {
                $original_dfd = $dfd;
                $dfd = new Deferred;
                $absorber = function ($any) use ($original_dfd) {
                    $original_dfd->resolve($any);
                };
                $dfd->promise()->then($absorber, $absorber);
            }
            // Add or enqueue cURL handles
            if (Utils::isCurl($yieldable['value'])) {
                $this->pool->addOrEnqueue($yieldable['value'], $dfd);
                continue;
            }
            // Process generators
            if (Utils::isGeneratorContainer($yieldable['value'])) {
                $this->processGeneratorContainer($yieldable['value'], $dfd);
                continue;
            }
        }
        return \mpyw\RuntimePromise\all($promises);
    }
}
