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
            $msg = curl_multi_strerror($errno) . ": $ch";
            $deferred && $deferred->reject(new \RuntimeException($msg));
            return;
        }
        $this->added[(string)$ch] = $ch;
        $deferred && $this->deferreds[(string)$ch] = $deferred;
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
                if (isset($this->deferreds[(string)$entry['handle']])) {
                    $deferred = $this->deferreds[(string)$entry['handle']];
                    unset($this->deferreds[(string)$entry['handle']]);
                    $r instanceof CURLException ? $deferred->reject($r) : $deferred->resolve($r);
                }
            }
        } while ($this->added || $this->queue);
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
            unset($this->added[(string)$entry['handle']]);
            if ($this->queue) {
                $ch = array_shift($this->queue);
                $this->addOrEnqueue($ch);
            }
        }
        return $entries;
    }
}
