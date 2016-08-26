<?php

namespace mpyw\Co;
use mpyw\Co\Internal\TypeUtils;
use mpyw\Co\Internal\ControlUtils;
use mpyw\Co\Internal\YieldableUtils;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Co\Internal\Pool;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;

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
     * Return if Co::wait() is running.
     * @return bool
     */
    public static function isRunning()
    {
        return (bool)self::$self;
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
        $return = null;
        // For convenience, all values are wrapped into generator
        $con = YieldableUtils::normalize($this->getRootGenerator($throw, $value, $return));
        $promise = $this->processGeneratorContainerRunning($con);
        if ($promise instanceof ExtendedPromiseInterface) {
            // This is actually 100% true; just used for unwrapping Exception thrown.
            $promise->done();
        }
        // We have to wait $return only if $wait
        if ($wait) {
            $this->pool->wait();
            return $return;
        }
    }

    /**
     * Handle resolving generators.
     * @param  GeneratorContainer $gc
     * @return PromiseInterface
     */
    private function processGeneratorContainer(GeneratorContainer $gc)
    {
        return $gc->valid()
            ? $this->processGeneratorContainerRunning($gc)
            : $this->processGeneratorContainerDone($gc);
    }

    /**
     * Handle resolving generators already done.
     * @param  GeneratorContainer $gc
     * @return PromiseInterface
     */
    private function processGeneratorContainerDone(GeneratorContainer $gc)
    {
        // If exception has been thrown in generator, we have to propagate it as rejected value
        if ($gc->thrown()) {
            return new RejectedPromise($gc->getReturnOrThrown());
        }

        // Now we normalize returned value
        $returned = YieldableUtils::normalize($gc->getReturnOrThrown(), $gc->getYieldKey());
        $yieldables = YieldableUtils::getYieldables($returned, [], $this->runners);

        // If normalized value contains yieldables, we have to chain resolver
        if ($yieldables) {
            $deferred = new Deferred;
            return $this->promiseAll($yieldables, true)
            ->then(
                YieldableUtils::getApplier($returned, $yieldables, [$deferred, 'resolve']),
                [$deferred, 'reject']
            )
            ->then(function () use ($yieldables, $deferred) {
                $this->runners = array_diff_key($this->runners, $yieldables);
                return $deferred->promise();
            });
        }

        // Propagate normalized returned value
        return new FulfilledPromise($returned);
    }

    /**
     * Handle resolving generators still running.
     * @param  GeneratorContainer $gc
     * @return PromiseInterface
     */
    private function processGeneratorContainerRunning(GeneratorContainer $gc)
    {
        // Check delay request yields
        if ($gc->key() === CoInterface::DELAY) {
            return $this->pool->addDelay($gc->current())
            ->then(function () use ($gc) {
                $gc->send(null);
                return $this->processGeneratorContainer($gc);
            });
        }

        // Now we normalize yielded value
        $yielded = YieldableUtils::normalize($gc->current());
        $yieldables = YieldableUtils::getYieldables($yielded, [], $this->runners);
        if (!$yieldables) {
            // If there are no yieldables, send yielded value back into generator
            $gc->send($yielded);
            // Continue
            return $this->processGeneratorContainer($gc);
        }

        // Chain resolver
        return $this->promiseAll($yieldables, $gc->key() !== CoInterface::SAFE)
        ->then(
            YieldableUtils::getApplier($yielded, $yieldables, [$gc, 'send']),
            [$gc, 'throw_']
        )->then(function () use ($gc, $yieldables) {
            // Continue
            $this->runners = array_diff_key($this->runners, $yieldables);
            return $this->processGeneratorContainer($gc);
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
            return;
        } catch (\Throwable $e) {} catch (\Exception $e) {}
        $this->pool->reserveHaltException($e);
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
            // Add or enqueue cURL handles
            if (TypeUtils::isCurl($yieldable['value'])) {
                $promises[(string)$yieldable['value']] = $this->pool->addCurl($yieldable['value']);
                continue;
            }
            // Process generators
            if (TypeUtils::isGeneratorContainer($yieldable['value'])) {
                $promises[(string)$yieldable['value']] = $this->processGeneratorContainer($yieldable['value']);
                continue;
            }
        }
        // If caller cannot accept exception,
        // we handle rejected value as resolved.
        if (!$throw_acceptable) {
            $promises = array_map(
                ['\mpyw\Co\Internal\YieldableUtils', 'safePromise'],
                $promises
            );
        }
        return \React\Promise\all($promises);
    }

    /**
     * Wrap value with the Generator that returns the first successful result.
     * If all yieldables failed, AllFailedException is thrown.
     * If no yieldables found, AllFailedException is thrown.
     *
     * @param  mixed $value
     * @return \Generator Resolved value.
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
     * @return \Generator Resolved value.
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
     * @return \Generator Resolved value.
     * @throws \RuntimeException
     */
    public static function all($value)
    {
        yield Co::RETURN_WITH => (yield $value);
        // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd
}
