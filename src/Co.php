<?php

namespace mpyw\Co;
use mpyw\Co\Internal\TypeUtils;
use mpyw\Co\Internal\ControlUtils;
use mpyw\Co\Internal\YieldableUtils;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Co\Internal\Pool;
use mpyw\Co\Internal\ControlException;

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
     * @var Pool
     */
    private $pool;

    /**
     * Running cURL or Generator identifiers.
     * @var array
     */
    private $runners = [];

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
     */
    public static function wait($value, array $options = [])
    {
        try {
            if (self::$self) {
                throw new \BadMethodCallException('Co::wait() is already running. Use Co::async() instead.');
            }
            self::$self = new self;
            self::$self->options = new CoOption($options);
            self::$self->pool = new Pool(self::$self->options);
            return self::$self->start($value);
        } finally {
            self::$self = null;
        }
        // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * Value is recursively resolved, but we never wait it.
     * This function must be called along with Co::wait().
     * @param  mixed $value
     * @param  mixed $throw
     */
    public static function async($value, $throw = null)
    {
        if (!self::$self) {
            throw new \BadMethodCallException('Co::async() must be called along with Co::wait(). ');
        }
        if ($throw !== null) {
            $throw = filter_var($throw, FILTER_VALIDATE_BOOLEAN, [
                'flags' => FILTER_NULL_ON_FAILURE,
            ]);
            if ($throw === null) {
                throw new \InvalidArgumentException("\$throw must be null or boolean.");
            }
        }
        self::$self->start($value, false, $throw);
    }

    /**
     * External instantiation is forbidden.
     */
    private function __construct() {}

    /**
     * Start resovling.
     * @param  mixed    $value
     * @param  bool     $wait
     * @param  mixed    $throw  Used for Co::async() overrides.
     * @param  mixed    If $wait, return resolved value.
     */
    private function start($value, $wait = true, $throw = null)
    {
        $deferred = new Deferred;
        $return = null;
        // For convenience, all values are wrapped into generator
        $con = YieldableUtils::normalize($this->getRootGenerator($throw, $value, $return));
        // We have to provide deferred object only if $wait
        $this->processGeneratorContainerRunning($con, $deferred);
        // We have to wait $return only if $wait
        if ($wait) {
            $this->pool->wait();
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
        $gc->valid()
        ? $this->processGeneratorContainerRunning($gc, $deferred)
        : $this->processGeneratorContainerDone($gc, $deferred);
    }

    /**
     * Handle resolving generators already done.
     * @param  GeneratorContainer $gc
     * @param  Deferred           $deferred
     */
    private function processGeneratorContainerDone(GeneratorContainer $gc, Deferred $deferred)
    {
        // If exception has been thrown in generator, we have to propagate it as rejected value
        if ($gc->thrown()) {
            $deferred->reject($gc->getReturnOrThrown());
            return;
        }

        // Now we normalize returned value
        $returned = YieldableUtils::normalize($gc->getReturnOrThrown(), $gc->getYieldKey());
        $yieldables = YieldableUtils::getYieldables($returned, [], $this->runners);

        // If normalized value contains yieldables, we have to chain resolver
        if ($yieldables) {
            $this
            ->promiseAll($yieldables, true)
            ->then(
                YieldableUtils::getApplier($returned, $yieldables, [$deferred, 'resolve']),
                [$deferred, 'reject']
            )
            ->always(function () use ($yieldables) {
                $this->runners = array_diff_key($this->runners, $yieldables);
            });
            return;
        }

        // Propagate normalized returned value
        $deferred->resolve($returned);
    }

    /**
     * Handle resolving generators still running.
     * @param  GeneratorContainer $gc
     * @param  Deferred           $deferred
     */
    private function processGeneratorContainerRunning(GeneratorContainer $gc, Deferred $deferred)
    {
        // Check delay request yields
        if ($gc->key() === CoInterface::DELAY) {
            $dfd = new Deferred;
            $this->pool->addDelay($gc->current(), $dfd);
            $dfd
            ->promise()
            ->then(function () use ($gc) {
                $gc->send(null);
            })
            ->always(function () use ($gc, $deferred) {
                $this->processGeneratorContainer($gc, $deferred);
            });
            return;
        }

        // Now we normalize yielded value
        $yielded = YieldableUtils::normalize($gc->current());
        $yieldables = YieldableUtils::getYieldables($yielded, [], $this->runners);
        if (!$yieldables) {
            // If there are no yieldables, send yielded value back into generator
            $gc->send($yielded);
            // Continue
            $this->processGeneratorContainer($gc, $deferred);
            return;
        }

        // Chain resolver
        $this
        ->promiseAll($yieldables, $gc->key() !== CoInterface::SAFE)
        ->then(
            YieldableUtils::getApplier($yielded, $yieldables, [$gc, 'send']),
            [$gc, 'throw_']
        )->always(function () use ($gc, $deferred, $yieldables) {
            // Continue
            $this->runners = array_diff_key($this->runners, $yieldables);
            $this->processGeneratorContainer($gc, $deferred);
        });
    }

    /**
     * Return root wrapper generator.
     * @param  mixed  $throw
     * @param  mixed  $value
     * @param  mixed  &$return
     */
    private function getRootGenerator($throw, $value, &$return)
    {
        try {
            if ($throw !== null) {
                $key = $throw ? null : CoInterface::SAFE;
            } else {
                $key = $this->options['throw'] ? null : CoInterface::SAFE;
            }
            $return = (yield $key => $value);
        } catch (\RuntimeException $e) {
            $this->pool->reserveHaltException($e);
        }
    }

    /**
     * Promise all changes in yieldables are prepared.
     * @param  array $yieldables
     * @param  bool  $throw_acceptable
     * @return PromiseInterface
     */
    private function promiseAll(array $yieldables, $throw_acceptable)
    {
        $promises = [];
        foreach ($yieldables as $yieldable) {
            $dfd = new Deferred;
            $promises[(string)$yieldable['value']] = $dfd->promise();
            // If caller cannot accept exception,
            // we handle rejected value as resolved.
            if (!$throw_acceptable) {
                $dfd = YieldableUtils::safeDeferred($dfd);
            }
            // Add or enqueue cURL handles
            if (TypeUtils::isCurl($yieldable['value'])) {
                $this->pool->addCurl($yieldable['value'], $dfd);
                continue;
            }
            // Process generators
            if (TypeUtils::isGeneratorContainer($yieldable['value'])) {
                $this->processGeneratorContainer($yieldable['value'], $dfd);
                continue;
            }
        }
        return \mpyw\RuntimePromise\all($promises);
    }

    /**
     * Wrap value with the Generator that returns the first successful result.
     * If all yieldables failed, AllFailedException is thrown.
     * If no yieldables found, AllFailedException is thrown.
     *
     * @param  mixed $value
     * @return mixed Resolved value.
     * @throws AllFailedException
     */
    public static function any($value)
    {
        yield Co::RETURN_WITH => (yield ControlUtils::anyOrRace(
            $value,
            ['\mpyw\Co\Internal\ControlUtils', 'reverse'],
            'Co::any() failed.'
        ));
        // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * Wrap value with the Generator that returns the first result.
     * If no yieldables found, AllFailedException is thrown.
     *
     * @param  mixed $value
     * @return mixed Resolved value.
     * @throws \RuntimeException|AllFailedException
     */
    public static function race($value)
    {
        yield Co::RETURN_WITH => (yield ControlUtils::anyOrRace(
            $value,
            ['\mpyw\Co\Internal\ControlUtils', 'fail'],
            'Co::race() failed.'
        ));
        // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * Wrap value with the Generator that returns the all results.
     * Normally you don't have to use this method, just yield an array that contains yieldables.
     * You should use only with Co::race() or Co::any().
     *
     * @param  mixed $value
     * @return mixed Resolved value.
     * @throws \RuntimeException
     */
    public static function all($value)
    {
        yield Co::RETURN_WITH => (yield $value);
        // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd
}
