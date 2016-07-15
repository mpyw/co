<?php

namespace mpyw\Co\Internal;

class Dispatcher
{
    /**
     * Event subscribers.
     * @var array
     */
    private $subscribers = [];

    /**
     * Dispatch callback for the event.
     * @param  string  $event
     * @param  Closure $callback
     * @param  bool    $once
     */
    public function dispatch($event, \Closure $callback, $once = false)
    {
        $hash = spl_object_hash($callback);
        $this->subscribers[$event] = [
            'callback' => $callback,
            'once' => $once,
        ];
    }

    /**
     * Dispatch callback for the event.
     * @param  string  $event
     * @param  Closure $callback
     */
    public function dispatchOnce($event, \Closure $callback)
    {
        $this->dispatch($event, $callback, true);
    }

    /**
     * Remove callback from the event.
     * @param  string  $event
     */
    public function remove($event)
    {
        unset($this->subscribers[$event]);
    }

    /**
     * Notify the event to callbacks.
     * @param  string  $event
     * @param  mixed   ...$args  Arguments.
     */
    public function notify($event, ...$args)
    {
        if (empty($this->subscribers[$event])) {
            return;
        }
        $setting = $this->subscribers[$event];
        $callback = $setting['callback'];
        $callback(...$args);
        if ($setting['once']) {
            $this->remove($event);
        }
    }
}
