<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\CURLException;
use mpyw\RuntimePromise\Deferred;

class ConnectionCounter
{
    /**
     * Options.
     * @var CoOption
     */
    private $options;

    /**
     * The number of whole running TCP connections.
     * @var array
     */
    private $connectionCount = 0;

    /**
     * Counts per destination.
     *   key   => identifier
     *   value => count
     * @var array
     */
    private $destinations = [];

    /**
     * Constructor.
     * @param CoOption $options
     */
    public function __construct(CoOption $options)
    {
        $this->options = $options;
    }

    /**
     * Add destination info.
     * @param resource $ch
     */
    public function addDestination($ch)
    {
        $id = $this->getIdentifier($ch);
        if ($id === '') {
            ++$this->connectionCount;
            return;
        }
        if (empty($this->destinations[$id])) {
            $this->destinations[$id] = 1;
            ++$this->connectionCount;
            return;
        }
        ++$this->destinations[$id];
    }

    /**
     * Check if internal cURL pool is filled.
     * @param resource $ch
     * @return bool
     */
    public function isPoolFilled($ch)
    {
        $id = $this->getIdentifier($ch);
        if ($id !== '' && !empty($this->destinations[$id])) {
            return false;
        }
        return $this->options['concurrency'] > 0
            && $this->connectionCount >= $this->options['concurrency'];
    }

    /**
     * Remove destination info.
     * @param resource $ch
     */
    public function removeDestination($ch)
    {
        $id = $this->getIdentifier($ch);
        if ($id === '') {
            --$this->connectionCount;
            return;
        }
        if (empty($this->destinations[$id]) || $this->destinations[$id] === 1) {
            unset($this->destinations[$id]);
            --$this->connectionCount;
            return;
        }
        --$this->destinations[$id];
    }

    /**
     * Push into queue.
     * @param resource $ch
     * @return string
     */
    private function getIdentifier($ch)
    {
        return $this->options['group'] ? (string)curl_getinfo($ch, CURLINFO_PRIVATE) : '';
    }
}
