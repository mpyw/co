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

        $this->co = self::$Co::new([self::$Co::getStatic('defaults')]);

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

            for ($i = 1; $i < $this->co->options['concurrency']; ++$i) {
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
        $this->specify('Values to the nested positions should be correctly set',
        function () {
            $co = self::$Co::new([self::$Co::getStatic('defaults')]);
            $gen = (function () { yield 1; })();
            $co->tree = ['wait' => ['a', $gen, ['b', 'c'], 'd']];
            $co->setTree('e', 'wait', [4, 0]);
            $co->setTree('f', 'wait', [4, 'foo']);
            $co->setTree('Generator was replaced', 'wait', [1, 3, 0]);
            $this->assertEquals(
                ['a', [3 => ['Generator was replaced']], ['b', 'c'], 'd', ['e', 'foo' => 'f']],
                $co->tree['wait']
            );
        });
    }

    public function testUnsetTree()
    {
        $this->specify('Specified generator stack should be disposed, including decsendants',
        function () {
            $co = self::$Co::new([self::$Co::getStatic('defaults')]);
            $genfunc = function () { yield 1; };
            $gen1 = $genfunc(); $hash1 = spl_object_hash($gen1);
            $gen2 = $genfunc(); $hash2 = spl_object_hash($gen2);
            $gen3 = $genfunc(); $hash3 = spl_object_hash($gen3);
            $gen4 = $genfunc(); $hash4 = spl_object_hash($gen4);
            $co->tree = [
                'wait' => ['a', $gen1],
                $hash1 => ['b', [$gen2, $gen3]],
                $hash2 => 'c',
                $hash3 => [['foo' => $gen4]],
                $hash4 => 'd',
            ];
            $co->unsetTree($hash3);
            $this->assertEquals(['wait', $hash1, $hash2], array_keys($co->tree));
            $co->unsetTree($hash1);
            $this->assertEquals(['wait'], array_keys($co->tree));
        });
    }

    public function testSetTable()
    {
        $this->specify('Maps for Generators should be correctly built',
        function () {
            $co = self::$Co::new([self::$Co::getStatic('defaults')]);
            $gen = (function () { yield 1; })();
            $hash = spl_object_hash($gen);
            $co->setTable($gen, 'wait', [0, 0]);
            $this->assertEquals($gen, $co->values[$hash]);
            $this->assertEquals('wait', $co->value_to_parent[$hash]);
            $this->assertTrue($co->value_to_children['wait'][$hash]);
            $this->assertEquals([0, 0], $co->value_to_keylist[$hash]);
        });

        $this->specify('Maps for cURL resources should be correctly built',
        function () {
            $co = self::$Co::new([self::$Co::getStatic('defaults')]);
            $curl = curl_init();
            $hash = (string)$curl;
            $co->setTable($curl, 'wait', [0, 0]);
            $this->assertEquals($curl, $co->values[$hash]);
            $this->assertEquals('wait', $co->value_to_parent[$hash]);
            $this->assertTrue($co->value_to_children['wait'][$hash]);
            $this->assertEquals([0, 0], $co->value_to_keylist[$hash]);
        });
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
