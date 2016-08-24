<?php

namespace mpyw\Co\Internal;
use mpyw\Co\CURLException;
use React\Promise\PromiseInterface;

abstract class AbstractScheduler
{
    /**
     * cURL multi handle.
     * @var resource
     */
    protected $mh;

    /**
     * Options.
     * @var CoOption
     */
    protected $options;

    /**
     * cURL handles those have been already dispatched.
     * @var array
     */
    protected $added = [];

    /**
     * Deferreds.
     * @var array
     */
    protected $deferreds = [];

    /**
     * Constructor.
     * Initialize cURL multi handle.
     * @param CoOption $options
     * @param resource $mh      curl_multi
     */
    abstract public function __construct(CoOption $options, $mh);

    /**
     * Call curl_multi_add_handle() or push into queue.
     * @param resource $ch
     * @return PromiseInterface
     */
    abstract public function add($ch);

    /**
     * Are there no cURL handles?
     * @return bool
     */
    abstract public function isEmpty();

    /**
     * Do somthing with consumed handle.
     */
    abstract protected function interruptConsume();

    /**
     * Poll completed cURL entries, consume cURL queue and resolve them.
     */
    public function consume()
    {
        $entries = $this->readCompletedEntries();
        foreach ($entries as $entry) {
            curl_multi_remove_handle($this->mh, $entry['handle']);
            unset($this->added[(string)$entry['handle']]);
            $this->interruptConsume();
        }
        $this->resolveEntries($entries);
    }

    /**
     * Poll completed cURL entries.
     * @return array
     */
    protected function readCompletedEntries()
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
    protected function resolveEntries(array $entries)
    {
        foreach ($entries as $entry) {
            $deferred = $this->deferreds[(string)$entry['handle']];
            unset($this->deferreds[(string)$entry['handle']]);
            $entry['result'] === CURLE_OK
                ? $deferred->resolve(curl_multi_getcontent($entry['handle']))
                : $deferred->reject(new CURLException(curl_error($entry['handle']), $entry['result'], $entry['handle']));
        }
    }
}
