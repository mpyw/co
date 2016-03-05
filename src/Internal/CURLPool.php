<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;
use mpyw\Co\Internal\CallStack;

class CURLPool {

    private $mh;                          // curl_multi_init()
    private $count = 0;                   // count(curl_multi_add_handle called)
    private $queue = array();             // cURL resources over concurrency limits are temporalily stored here
    private $co;
    private $parents = array();

    public function __construct(Co $co)
    {
        $this->co = $co;
        $this->initMultiHandle();
    }

    private function initMultiHandle()
    {
        $this->mh = curl_multi_init();
        if (function_exists('curl_multi_setopt')) {
            $flags =
                ($this->co->options['pipeline'] ? 1 : 0)
                | ($this->co->options['multiplex'] ? 2 : 0)
            ;
            curl_multi_setopt($this->mh, CURLMOPT_PIPELINING, $flags);
        }
    }

    /**
     * Call curl_multi_add_handle or push into waiting queue.
     *
     * @access private
     * @param resource $ch
     */
    public function enqueue($ch, CallStack $stack)
    {
        if ($this->concurrency && $this->count >= $this->co->options['concurrency']) {
            if (isset($this->queue[(string)$ch])) {
                throw new \InvalidArgumentException("The cURL resource is already enqueued: $ch");
            }
            $this->queue[(string)$ch] = $ch;
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
        }
        $this->parents[(string)$ch] = $stack;
    }

    /**
     * Run curl_multi_exec() loop.
     *
     * @access private
     * @see self::updateCurl(), self::enqueue()
     */
    public function wait($updator)
    {
        curl_multi_exec($this->mh, $active); // Start requests.
        do {
            curl_multi_select($this->mh, $this->co->options['interval']); // Wait events.
            curl_multi_exec($this->mh, $callstack);
            foreach ($this->readEntries() as $entry) {
                $callback = $this->parents[(string)$entry['handle']];
                $callback($entry['handle'], $entry['result']);
            }
        } while ($this->count > 0 || $this->queue);
        // All request must be done when reached here.
        if ($active) {
            throw new \LogicException('Unreachable statement.');
        }
    }

    private function readEntries()
    {
        $entries = array();
        while ($entry = curl_multi_info_read($this->mh)) {
            $entries[] = $entry;
        }
        foreach ($entries as $entry) {
            curl_multi_remove_handle($this->mh, $entry['handle']);
            --$this->count;
            if ($this->queue) {
                $this->enqueue(array_shift($this->queue));
            }
        }
        return $entries;
    }

}
