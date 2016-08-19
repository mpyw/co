<?php

namespace mpyw\Co\Internal;
use mpyw\Co\CURLException;
use mpyw\RuntimePromise\Deferred;

class Scheduler
{
    /**
     * cURL multi handle.
     * @var resource
     */
    private $mh;

    /**
     * Options.
     * @var CoOption
     */
    private $options;

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
     * Deferreds.
     * @var Deferred
     */
    private $deferreds = [];

    /**
     * TCP connection counter.
     * @var ConnectionCounter
     */
    private $counter;

    /**
     * Constructor.
     * Initialize cURL multi handle.
     * @param CoOption $options
     * @param resource $mh      curl_multi
     */
    public function __construct(CoOption $options, $mh)
    {
        $this->mh = $mh;
        $this->options = $options;
        $this->counter = new ConnectionCounter($options);
    }

    /**
     * Call curl_multi_add_handle() or push into queue.
     * @param resource $ch
     * @param Deferred $deferred
     */
    public function add($ch, Deferred $deferred = null)
    {
        $this->counter->isPoolFilled($ch)
            ? $this->addReserved($ch, $deferred)
            : $this->addImmediate($ch, $deferred);
    }

    /**
     * Are there no cURL handles?
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->added && !$this->queue;
    }

    /**
     * Call curl_multi_add_handle().
     * @param resource $ch
     * @param Deferred $deferred
     */
    private function addImmediate($ch, Deferred $deferred = null)
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
    private function addReserved($ch, Deferred $deferred = null)
    {
        $this->queue[(string)$ch] = $ch;
        $deferred && $this->deferreds[(string)$ch] = $deferred;
    }

    /**
     * Poll completed cURL entries, consume cURL queue and resolve them.
     */
    public function consume()
    {
        $entries = $this->readCompletedEntries();
        foreach ($entries as $entry) {
            curl_multi_remove_handle($this->mh, $entry['handle']);
            unset($this->added[(string)$entry['handle']]);
            $this->counter->removeDestination($entry['handle']);
            $this->queue && $this->add(array_shift($this->queue));
        }
        $this->resolveEntries($entries);
    }

    /**
     * Poll completed cURL entries.
     * @return array
     */
    private function readCompletedEntries()
    {
        $entries = [];
        // DO NOT call curl_multi_add_handle() until polling done
        while ($entry = curl_multi_info_read($this->mh)) {
            $entries[] = $entry;
        }
        return $entries;
    }

    /**
     * Resolve polled cURLs.
     * @param  array $entries Polled cURL entries.
     */
    private function resolveEntries(array $entries)
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
