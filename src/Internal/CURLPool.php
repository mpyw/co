<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\CURLException;
use mpyw\RuntimePromise\Deferred;

class CURLPool
{
    /**
     * Options.
     * @var CoOption
     */
    private $options;

    /**
     * cURL multi handle.
     * @var resource
     */
    private $mh;

    /**
     * cURL handles those have not been dispatched.
     * @var array
     */
    private $queue = [];

    /**
     * cURL handles those have been already dispatched.
     * @var array
     */
    private $added = [];

    /**
     * Delays to be ended at.
     * @var array
     */
    private $untils = [];

    /**
     * React Deferreds.
     * @var Deferred
     */
    private $deferreds = [];

    /**
     * Used for halting loop.
     * @var \RuntimeException
     */
    private $haltException;

    /**
     * Constructor.
     * Initialize cURL multi handle.
     * @param CoOption $options
     */
    public function __construct(CoOption $options)
    {
        $this->options = $options;
        $this->mh = curl_multi_init();
        $flags = (int)$options['pipeline'] + (int)$options['multiplex'] * 2;
        curl_multi_setopt($this->mh, CURLMOPT_PIPELINING, $flags);
    }

    /**
     * Call curl_multi_add_handle() or push into queue.
     * @param resource $ch
     * @param Deferred $deferred
     */
    public function addOrEnqueue($ch, Deferred $deferred = null)
    {
        if (isset($this->added[(string)$ch]) || isset($this->queue[(string)$ch])) {
            throw new \InvalidArgumentException("The cURL handle is already enqueued: $ch");
        }
        if (count($this->added) >= $this->options['concurrency']) {
            $this->queue[(string)$ch] = $ch;
            $deferred && $this->deferreds[(string)$ch] = $deferred;
            return;
        }
        $errno = curl_multi_add_handle($this->mh, $ch);
        if ($errno !== CURLM_OK) {
            // @codeCoverageIgnoreStart
            $msg = curl_multi_strerror($errno) . ": $ch";
            $deferred && $deferred->reject(new \RuntimeException($msg));
            return;
            // @codeCoverageIgnoreEnd
        }
        $this->added[(string)$ch] = $ch;
        $deferred && $this->deferreds[(string)$ch] = $deferred;
    }

    /**
     * Add delay.
     * @param int      $time
     * @param Deferred $deferred
     */
    public function addDelay($time, Deferred $deferred)
    {
        $time = filter_var($time, FILTER_VALIDATE_FLOAT);
        if ($time === false || $time < 0) {
            throw new \InvalidArgumentException('Delay must be positive number.');
        }
        $now = microtime(true);
        $until = $now + $time;
        $diff = $until - $now;
        if ($diff <= 0.0) {
            // @codeCoverageIgnoreStart
            $deferred->resolve(null);
            return;
            // @codeCoverageIgnoreEnd
        }
        do {
            $id = uniqid();
        } while (isset($this->untils[$id]));
        $this->untils[$id] = $until;
        $this->deferreds[$id] = $deferred;
    }

    /**
     * Run curl_multi_exec() loop.
     */
    public function wait()
    {
        $this->haltException = false;
        curl_multi_exec($this->mh, $active); // Start requests.
        do {
            if ($this->added || $this->queue) {
                // if cURL handle is running, use curl_multi_select()
                curl_multi_select($this->mh, $this->options['interval']) < 0
                && usleep($this->options['interval'] * 1000000);
            } else {
                // otherwise, just sleep until nearest time
                $this->sleepUntilNearestTime();
            }
            curl_multi_exec($this->mh, $active);
            $this->consumeCurlsAndUntils();
            if ($this->haltException) {
                throw $this->haltException;
            }
        } while ($this->added || $this->queue || $this->untils);
    }

    /**
     * Used for halting loop.
     */
    public function reserveHaltException($e)
    {
        $this->haltException = $e;
    }

    /**
     * Sleep at least required.
     */
    private function sleepUntilNearestTime()
    {
        $now = microtime(true);
        $min = null;
        foreach ($this->untils as $id => $until) {
            $diff = $now - $until;
            if ($diff < 0) {
                continue;
            }
            if ($min === null || $diff < $min) {
                $min = $diff;
            }
        }
        if ($min !== null) {
            usleep($min * 1000000);
        }
    }

    /**
     * Consume completed cURL handles and delays.
     */
    private function consumeCurlsAndUntils()
    {
        $entries = [];
        // First, we have to poll completed entries
        // DO NOT call curl_multi_add_handle() until polling done
        while ($entry = curl_multi_info_read($this->mh)) {
            $entries[] = $entry;
        }
        // Remove entry from multi handle to consume queue if available
        foreach ($entries as $entry) {
            curl_multi_remove_handle($this->mh, $entry['handle']);
            unset($this->added[(string)$entry['handle']]);
            if ($this->queue) {
                $ch = array_shift($this->queue);
                $this->addOrEnqueue($ch);
            }
        }
        // Now we check specified delay time elapsed
        foreach ($this->untils as $id => $until) {
            $diff = $until - microtime(true);
            if ($diff <= 0.0 && isset($this->deferreds[$id])) {
                $deferred = $this->deferreds[$id];
                unset($this->deferreds[$id], $this->untils[$id]);
                $deferred->resolve(null);
            }
        }
        // Finally, resolve cURL responses
        foreach ($entries as $entry) {
            $r = $entry['result'] === CURLE_OK
                ? curl_multi_getcontent($entry['handle'])
                : new CURLException(curl_error($entry['handle']), $entry['result'], $entry['handle']);
            if (isset($this->deferreds[(string)$entry['handle']])) {
                $deferred = $this->deferreds[(string)$entry['handle']];
                unset($this->deferreds[(string)$entry['handle']]);
                $r instanceof CURLException ? $deferred->reject($r) : $deferred->resolve($r);
            }
        }
    }
}
