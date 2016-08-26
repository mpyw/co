<?php

namespace mpyw\Co\Internal;
use mpyw\Co\CURLException;
use React\Promise\PromiseInterface;

class Pool
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
     * Used for halting loop.
     * @var \Throwable|\Exception
     */
    private $haltException;

    /**
     * cURL handle scheduler.
     * @var AbstractScheduler
     */
    private $scheduler;

    /**
     * Delay controller.
     * @var Delayer
     */
    private $delayer;

    /**
     * Constructor.
     * Initialize cURL multi handle.
     * @param CoOption $options
     */
    public function __construct(CoOption $options)
    {
        $this->mh = curl_multi_init();
        $flags = (int)$options['pipeline'] + (int)$options['multiplex'] * 2;
        curl_multi_setopt($this->mh, CURLMOPT_PIPELINING, $flags);
        $this->options = $options;
        $this->scheduler = $options['autoschedule']
            ? new AutoScheduler($options, $this->mh)
            : new ManualScheduler($options, $this->mh);
        $this->delayer = new Delayer;
    }

    /**
     * Call curl_multi_add_handle() or push into queue.
     * @param resource $ch
     * @return PromiseInterface
     */
    public function addCurl($ch)
    {
        return $this->scheduler->add($ch);
    }

    /**
     * Add delay.
     * @param int $time
     * @return PromiseInterface
     */
    public function addDelay($time)
    {
        return $this->delayer->add($time);
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
            $this->scheduler->isEmpty()
                ? $this->delayer->sleep()
                : curl_multi_select($this->mh, $this->options['interval']) < 0
                  && usleep($this->options['interval'] * 1000000);
            curl_multi_exec($this->mh, $active);
            $this->scheduler->consume();
            $this->delayer->consume();
        } while (!$this->haltException && (!$this->scheduler->isEmpty() || !$this->delayer->isEmpty()));
        if ($this->haltException) {
            throw $this->haltException;
        }
    }

    /**
     * Used for halting loop.
     * @param \Throwable|\RuntimeException $e
     */
    public function reserveHaltException($e)
    {
        $this->haltException = $e;
    }
}
