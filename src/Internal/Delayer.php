<?php

namespace mpyw\Co\Internal;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class Delayer
{
    /**
     * Delays to be ended at.
     * @var array
     */
    private $untils = [];

    /**
     * Deferreds.
     * @var array
     */
    private $deferreds = [];

    /**
     * Add delay.
     * @param int $time
     * @return PromiseInterface
     */
    public function add($time)
    {
        $deferred = new Deferred;
        $time = filter_var($time, FILTER_VALIDATE_FLOAT);
        if ($time === false) {
            throw new \InvalidArgumentException('Delay must be number.');
        }
        if ($time < 0) {
            throw new \DomainException('Delay must be positive.');
        }
        do {
            $id = uniqid();
        } while (isset($this->untils[$id]));
        $this->untils[$id] = microtime(true) + $time;
        $this->deferreds[$id] = $deferred;
        return $deferred->promise();
    }

    /**
     * Sleep at least required.
     */
    public function sleep()
    {
        $now = microtime(true);
        $min = null;
        foreach ($this->untils as $id => $until) {
            $diff = $until - $now;
            if ($diff < 0) {
                // @codeCoverageIgnoreStart
                return;
                // @codeCoverageIgnoreEnd
            }
            if ($min !== null && $diff >= $min) {
                continue;
            }
            $min = $diff;
        }
        $min && usleep($min * 1000000);
    }

    /**
     * Consume delay queue.
     */
    public function consume()
    {
        foreach ($this->untils as $id => $until) {
            $diff = $until - microtime(true);
            if ($diff > 0.0 || !isset($this->deferreds[$id])) {
                continue;
            }
            $deferred = $this->deferreds[$id];
            unset($this->deferreds[$id], $this->untils[$id]);
            $deferred->resolve(null);
        }
    }

    /**
     * Is $untils empty?
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->untils;
    }
}
