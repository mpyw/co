<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;
use mpyw\Co\Internal\Dispatcher;

class CURLPool
{
    /**
     * Options.
     * @var array
     */
    private $options;

    /**
     * cURL multi handle.
     * @var resource
     */
    private $mh;

    /**
     * The number of dispatched cURL handle.
     * @var int
     */
    private $count = 0;

    /**
     * cURL handles those have not been dispatched.
     * @var array
     */
    private $queue;

    /**
     * Constructor.
     * Initialize cURL multi handle.
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
        $this->mh = curl_multi_init();
        if (function_exists('curl_multi_setopt')) {
            $flags =
                ($this->options['pipeline'] ? 1 : 0)
                | ($this->options['multiplex'] ? 2 : 0)
            ;
            curl_multi_setopt($this->mh, CURLMOPT_PIPELINING, $flags);
        }
    }

    /**
     * Call curl_multi_add_handle() or push into waiting queue.
     * @param resource $ch
     */
    public function enqueue($ch)
    {
        if ($this->count >= $this->options['concurrency']) {
            if (isset($this->queue[(string)$ch])) {
                throw new \InvalidArgumentException("The cURL resource is already enqueued: $ch");
            }
            $this->queue[(string)$ch] = $ch;
            Dispatcher::notify('curl_enqueued-' . (string)$ch);
        } else {
            $errno = curl_multi_add_handle($this->mh, $ch);
            if ($errno !== CURLM_OK) {
                $msg = curl_multi_strerror($errno) . ": $ch";
                $class = $errno === 7 || $errno === CURLE_FAILED_INIT
                    ? 'InvalidArgumentException'
                    : 'RuntimeException'
                ;
                throw new $class($msg);
            }
            ++$this->count;
            Dispatcher::notify('curl_added-' . (string)$ch);
        }
    }

    /**
     * Run curl_multi_exec() loop.
     */
    public function wait()
    {
        curl_multi_exec($this->mh, $active); // Start requests.
        do {
            curl_multi_select($this->mh, $this->options['interval']); // Wait events.
            curl_multi_exec($this->mh, $active);
            foreach ($this->readEntries() as $entry) {
                Dispatcher::notify('curl_complete-' . $entry['handle'], $entry['handle'], $entry['result']);
                if ($this->queue) {
                    $ch = array_shift($this->queue);
                    $this->enqueue($ch);
                }
            }
        } while ($this->count > 0 || $this->queue);
        // All request must be done when reached here.
        if ($active) {
            throw new \LogicException('Unreachable statement.');
        }
    }

    /**
     * Read completed cURL handles.
     * @return array
     */
    private function readEntries()
    {
        $entries = array();
        while ($entry = curl_multi_info_read($this->mh)) {
            $entries[] = $entry;
        }
        foreach ($entries as $entry) {
            curl_multi_remove_handle($this->mh, $entry['handle']);
            --$this->count;
        }
        return $entries;
    }

    public function __sleep()
    {
        throw new \BadMethodCallException('Serialization is not supported.');
    }
}
