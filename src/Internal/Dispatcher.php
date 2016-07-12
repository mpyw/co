<?php

namespace mpyw\Co\Internal;

class Dispatcher
{
    /**
     * Event subscribers.
     * @var array
     */
    private static $subscribers = array();

    /**
     * Dummy constructor.
     */
    private function __construct() { }

    /**
     * Dispatch callback for the event.
     * @param  string  $event
     * @param  Closure $callback
     * @param  bool    $once
     */
    public static function dispatch($event, \Closure $callback, $once = false)
    {
        $hash = spl_object_hash($callback);
        self::$subscribers[$event][$hash] = [
            'callback' => $callback,
            'once' => $once,
        ];
    }

    /**
     * Dispatch callback for the event.
     * @param  string  $event
     * @param  Closure $callback
     */
    public static function dispatchOnce($event, \Closure $callback)
    {
        self::dispatch($event, $callback, true);
    }

    /**
     * Remove callback from the event.
     * @param  string  $event
     * @param  Closure $callback
     */
    public static function remove($event, \Closure $callback)
    {
        $hash = spl_object_hash($callback);
        if (isset(self::$subscribers[$event][$callback])) {
            unset(self::$subscribers[$event][$callback]);
        }
        if (empty(self::$subscribers[$event])) {
            unset(self::$subscribers[$event]);
        }
    }

    /**
     * Notify the event to callbacks.
     * @param  string  $event
     * @param  mixed   $event,... Arguments.
     */
    public static function notify($event)
    {
        $args = array_slice(func_get_args(), 1);
        if (empty(self::$subscribers[$event])) {
            return;
        }
        foreach (self::$subscribers[$event] as $setting) {
            call_user_func_array($setting['callback'], $args);
            if ($setting['once']) {
                self::remove($event, $callback);
            }
        }
    }
}
