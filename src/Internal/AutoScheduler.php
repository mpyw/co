<?php

namespace mpyw\Co\Internal;
use mpyw\Co\CURLException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class AutoScheduler extends AbstractScheduler
{
    /**
     * Constructor.
     * Initialize cURL multi handle.
     * @param CoOption $options
     * @param resource $mh      curl_multi
     */
    public function __construct(CoOption $options, $mh)
    {
        curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, $options['concurrency']);
        $this->mh = $mh;
        $this->options = $options;
    }

    /**
     * Call curl_multi_add_handle().
     * @param resource $ch
     * @return PromiseInterface
     */
    public function add($ch)
    {
        $deferred = new Deferred;
        $errno = curl_multi_add_handle($this->mh, $ch);
        if ($errno !== CURLM_OK) {
            // @codeCoverageIgnoreStart
            $msg = curl_multi_strerror($errno) . ": $ch";
            $deferred->reject(new \RuntimeException($msg));
            return $deferred->promise();
            // @codeCoverageIgnoreEnd
        }
        $this->added[(string)$ch] = $ch;
        $this->deferreds[(string)$ch] = $deferred;
        return $deferred->promise();
    }

    /**
     * Are there no cURL handles?
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->added;
    }

    /**
     * Do nothing.
     */
    protected function interruptConsume() {}
}
