<?php

use mpyw\Co\Co;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;

/**
 * @requires PHP 7.0
 */
class PrivateTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;
    private static $Co;

    public function _before()
    {
        self::$Co = Proxy::get(Co::class);
    }

    public function testConstructor()
    {
    }

    public function testEnqueue()
    {
        $func = test::func('mpyw\Co', 'curl_multi_add_handle', function (...$args) {
            return \curl_multi_add_handle(...$args);
        });

        $this->co = self::$Co::new([true, 0.5, 6]);

        $this->specify('Count and queue should be initialized with empty value',
        function () {
            $this->assertEquals(0, $this->co->count);
            $this->assertEquals([], $this->co->queue);
        });

        $this->specify('Count should increment within the concurrency limit',
        function () use ($func) {

            $this->specify('Duplicated cURL handle should be rejected',
            function () {
                $curl = curl_init();
                $this->co->enqueue($curl);
                $this->assertTrue(true);
                $this->co->enqueue($curl);
            }, ['throws' => 'InvalidArgumentException']);

            for ($i = 1; $i < $this->co->concurrency; ++$i) {
                $curl = curl_init();
                $this->co->enqueue($curl);
                $this->assertEquals($i + 1, $this->co->count);
                $func->verifyInvoked([$this->co->mh, $curl]);
            }

        });

        $this->specify('Overflowed ones should be queued',
        function () use ($func) {

            $this->specify('Duplicated cURL handle should be rejected',
            function () {
                $curl = curl_init();
                $this->co->enqueue($curl);
                $this->assertTrue(true);
                $this->co->enqueue($curl);
            }, ['throws' => 'InvalidArgumentException']);

            for ($i = 1; $i < 3; ++$i) {
                $curl = curl_init();
                $this->co->enqueue($curl);
                $this->assertEquals($i + 1, count($this->co->queue));
            }

        });

    }

    public function testSetTree()
    {
    }

    public function testUnsetTree()
    {
    }

    public function testSetTable()
    {
    }

    public function testUnsetTable()
    {
    }

    public function testInitialize()
    {
    }

    public function testUpdateCurl()
    {
    }

    public function testCanThrow()
    {
    }

    public function testUpdateGenerator()
    {
    }

    public function testRun()
    {
    }

}
