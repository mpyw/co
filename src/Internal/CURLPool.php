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
     * TCP connection counter.
     * @var ConnectionCounter
     */
    private $counter;

    /**
     * Constructor.
     * Initialize cURL multi handle.
     * @param CoOption $options
     */
    public function __construct(CoOption $options)
    {
        $this->options = $options;
        $this->counter = new ConnectionCounter($options);
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
            throw new \UnexpectedValueException("The cURL handle is already enqueued: $ch");
        }
        $this->counter->isPoolFilled($ch)
            ? $this->enqueueCurl($ch, $deferred)
            : $this->addCurl($ch, $deferred);
    }

    /**
     * Call curl_multi_add_handle().
     * @param resource $ch
     * @param Deferred $deferred
     */
    private function addCurl($ch, Deferred $deferred = null)
    {
        $errno = curl_multi_add_handle($this->mh, $ch);
        if ($errno !== CURLM_OK) {
            // @codeCoverageIgnoreStart
            $msg = curl_multi_strerror($errno) . ": $ch";
            $deferred && $deferred->reject(new \RuntimeException($msg));
            return;
            // @codeCoverageIgnoreEnd
        }
        $this->added[(string)$ch] = $ch;
        $this->counter->addDestination($ch);
        $deferred && $this->deferreds[(string)$ch] = $deferred;
    }

    /**
     * Push into queue.
     * @param resource $ch
     * @param Deferred $deferred
     */
    private function enqueueCurl($ch, Deferred $deferred = null)
    {
        $this->queue[(string)$ch] = $ch;
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
    }

    /**
     * Run curl_multi_exec() loop.
     */
    public function wait()
    {
        curl_multi_exec($this->mh, $active); // Start requests.
        do {
            // if cURL handle is running, use curl_multi_select()
            // otherwise, just sleep until nearest time
            $this->added || $this->queue
                ? curl_multi_select($this->mh, $this->options['interval']) < 0
                  && usleep($this->options['interval'] * 1000000)
                : $this->sleepUntilNearestTime();
            curl_multi_exec($this->mh, $active);
            $this->consumeCurlsAndUntils();
        } while (!$this->haltException && ($this->added || $this->queue || $this->untils));
        if ($this->haltException) {
            throw $this->haltException;
        }
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
     * Consume completed cURL handles and delays.
     */
    private function consumeCurlsAndUntils()
    {
        $entries = $this->consumeCurls();
        $this->consumeUntils();
        $this->resolveCurls($entries);
    }

    /**
     * Poll completed cURL entries and consume cURL queue.
     * @return array
     */
    private function consumeCurls()
    {
        $entries = [];
        // DO NOT call curl_multi_add_handle() until polling done
        while ($entry = curl_multi_info_read($this->mh)) {
            $entries[] = $entry;
        }
        foreach ($entries as $entry) {
            curl_multi_remove_handle($this->mh, $entry['handle']);
            unset($this->added[(string)$entry['handle']]);
            $this->counter->removeDestination($entry['handle']);
            $this->queue && $this->addOrEnqueue(array_shift($this->queue));
        }
        return $entries;
    }

    /**
     * Consume delay queue.
     */
    private function consumeUntils()
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
     * Resolve polled cURLs.
     * @param  array $entries Polled cURL entries.
     */
    private function resolveCurls($entries)
    {
        foreach ($entries as $entry) {
            if (!isset($this->deferreds[(string)$entry['handle']])) {
                continue;
            }
            $deferred = $this->deferreds[(string)$entry['handle']];
            unset($this->deferreds[(string)$entry['handle']]);
            $entry['result'] === CURLE_OK
                ? $deferred->resolve(curl_multi_getcontent($entry['handle']))
                : $deferred->reject(new CURLException(curl_error($entry['handle']), $entry['result'], $entry['handle']));
        }
    }
}
