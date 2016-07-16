<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\CURLException;
use React\Promise\Deferred;

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
     * The number of dispatched cURL handle.
     * @var int
     */
    private $count = 0;

    /**
     * cURL handles those have not been dispatched.
     * @var array
     */
    private $queue = [];

    /**
     * React Deferreds.
     * @var Deferred
     */
    private $deferreds = [];

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
    public function addOrEnqueue($ch, $deferred = null)
    {
        if ($this->count >= $this->options['concurrency']) {
            if (isset($this->queue[(string)$ch])) {
                throw new \InvalidArgumentException("The cURL handle is already enqueued: $ch");
            }
            $this->queue[(string)$ch] = $ch;
        } else {
            $errno = curl_multi_add_handle($this->mh, $ch);
            if ($errno !== CURLM_OK) {
                $msg = curl_multi_strerror($errno) . ": $ch";
                $class = $errno === 7 ? '\InvalidArgumentException' : '\RuntimeException';
                throw new $class($msg);
            }
            ++$this->count;
        }
        if ($deferred) {
            $this->deferreds[(string)$ch] = $deferred;
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
                $r = $entry['result'] === CURLE_OK
                    ? curl_multi_getcontent($entry['handle'])
                    : new CURLException(curl_error($entry['handle']), $entry['result'], $entry['handle']);
                if (isset($this->deferred[(string)$entry['result']])) {
                    $deferred = $this->deferred[(string)$entry['result']];
                    unset($this->deferred[(string)$entry['result']]);
                    $r instanceof CURLException ? $deferred->reject($r) : $deferred->resolve($r);
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
        $entries = [];
        while ($entry = curl_multi_info_read($this->mh)) {
            $entries[] = $entry;
        }
        foreach ($entries as $entry) {
            curl_multi_remove_handle($this->mh, $entry['handle']);
            --$this->count;
            if ($this->queue) {
                $ch = array_shift($this->queue);
                $this->addOrEnqueue($ch);
            }
        }
        return $entries;
    }
}
