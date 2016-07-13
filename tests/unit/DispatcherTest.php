<?php

use mpyw\Co\Internal\Dispatcher;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;

/**
 * @requires PHP 7.0
 */
class DispatcherTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;
    private static $Dispatcher;

    public function _before()
    {
        self::$Dispatcher = Proxy::get(Dispatcher::class);
    }

    public function _after()
    {

    }

    public function testDispatchOnce()
    {
        $invoked = 0;
        $cb1 = function (...$args) use (&$invoked) {
            $this->assertEquals($args, ['arg1', 'arg2']);
            ++$invoked;
        };
        $cb2 = function (...$args) use (&$invoked) {
            $this->assertEquals($args, ['arg1', 'arg2']);
            ++$invoked;
        };
        $h1 = spl_object_hash($cb1);
        $h2 = spl_object_hash($cb2);

        $dispatcher = self::$Dispatcher::new();
        $dispatcher->dispatchOnce('event-once', $cb1);
        $dispatcher->dispatchOnce('event-once', $cb2);
        $this->assertEquals($invoked, 0);
        $this->assertArrayHasKey('event-once', $dispatcher->subscribers);
        $this->assertArrayHasKey($h1, $dispatcher->subscribers['event-once']);
        $this->assertArrayHasKey($h2, $dispatcher->subscribers['event-once']);

        $dispatcher->notify('event-once', 'arg1', 'arg2');
        $this->assertEquals($invoked, 2);
        $this->assertArrayNotHasKey('event-once', $dispatcher->subscribers);
    }

    public function testDispatchAndRemove()
    {
        $dispatcher = self::$Dispatcher::new();
        $invoked = 0;
        $cb = function () use (&$invoked, $dispatcher, &$cb) {
            ++$invoked;
            if ($invoked === 2) {
                $dispatcher->remove('event', $cb);
            }
        };
        $h = spl_object_hash($cb);

        $dispatcher->dispatch('event', $cb);

        $this->assertEquals($invoked, 0);
        $this->assertArrayHasKey('event', $dispatcher->subscribers);
        $this->assertArrayHasKey($h, $dispatcher->subscribers['event']);

        $dispatcher->notify('event');

        $this->assertEquals($invoked, 1);
        $this->assertArrayHasKey('event', $dispatcher->subscribers);
        $this->assertArrayHasKey($h, $dispatcher->subscribers['event']);

        $dispatcher->notify('event');

        $this->assertEquals($invoked, 2);
        $this->assertArrayNotHasKey('event', $dispatcher->subscribers);
    }

}
