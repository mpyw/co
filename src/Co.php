<?php

namespace mpyw\Co;
use mpyw\Co\Internal\Utils;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Co\Internal\CURLPool;
use React\Promise\all;
use React\Promise\Deferred;

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
     * Return values for Co::wait().
     * @var mixed
     */
    private $return;

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
            self::$self->start($value, new CoOption($options));
            $return = self::$self->return;
        } catch (\Throwable $e) {
            throw $e;
        } catch (\Exception $e) { // For both PHP7+ and PHP5
            throw $e;
        } finally {
            self::$self = null;
        }
        return $return;
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
        self::$self->start($value, self::$self->options->reconfigure($options), false);
    }

    /**
     * External instantiation is forbidden.
     */
    private function __construct() {}

    /**
     * Start resovling.
     * @param  mixed    $value
     * @param  CoOption $options
     * @param  bool     $wait
     */
    private function start($value, CoOption $options, $wait = true)
    {
        $this->options = $options;
        $this->pool = new CURLPool($options);
        if ($wait) {
            $deferred = new Deferred;
            $deferred->promise()->done(function ($return) {
                $this->return = $return;
            });
        }
        $genfunc = function () use ($value) {
            yield CoInterface::RETURN_WITH => (yield $value);
        };
        $con = Utils::normalize($genfunc, self::$options);
        $this->processGeneratorContainer($con);
        if ($wait) {
            $this->pool->wait();
        }
    }

    /**
     * Handle resolving generators.
     * @param  GeneratorContainer $gc
     * @param  Deferred           $deferred
     */
    private function processGeneratorContainer(GeneratorContainer $gc, Deferred $deferred = null)
    {
        if (!$gc->valid()) {
            try {
                $r = Utils::normalize($gc->getReturnOrThrown(), $gc->getOptions());
                !$gc->thrown() ? $deferred->resolve($r) : $deferred->reject($r);
            } catch (\RuntimeException $e) {
                !$gc->getOptions()['throw'] ? $deferred->resolve($e) : $deferred->reject($e);
            }
            return;
        }
        try {
            $yielded = Utils::normalize($gc->current(), $gc->getOptions(), $gc->key());
        } catch (\RuntimeException $e) {
            !$gc->getOptions()['throw'] ? $deferred->resolve($e) : $deferred->reject($e);
            return;
        }
        $yieldables = Utils::getYieldables($yielded);
        $promises = [];
        foreach ($yieldables as $yieldable) {
            $dfd = new Deferred;
            $promises[(string)$yieldable['value']] = $dfd->promise();
            if (self::isCurl($yieldable['value'])) {
                if (!$gc->getOptions()['throw']) {
                    $original_dfd = $dfd;
                    $dfd = new Deferred;
                    $absorber = function ($any) use ($original_dfd) {
                        $original_dfd->resolve($any);
                    };
                    $dfd->promise()->then($absorber, $absorber);
                }
                $this->pool->addOrEnqueue($dfd, $yieldable['value']);
                continue;
            }
            if (self::isGeneratorContainer($yieldable['value'])) {
                $this->processGeneratorContainer($dfd, $yieldable['value']);
                continue;
            }
        }
        all($promises)->then(
            function (array $results) use ($gc, $yielded, $yieldables, $deferred) {
                foreach ($results as $hash => $resolved) {
                    $current = &$yielded;
                    foreach ($yieldables[$hash]['keylist'] as $key) {
                        $current = &$current[$key];
                    }
                    $current = $resolved;
                }
                $gc->send($yielded);
                $this->processGeneratorContainer($deferred, $gc);
            },
            function (\RuntimeException $e) use ($gc, $deferred) {
                !$gc->getOptions()['throw'] ? $deferred->resolve($e) : $deferred->reject($e);
            }
        );
    }
}
