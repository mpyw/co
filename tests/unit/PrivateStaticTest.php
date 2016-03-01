<?php

use mpyw\Co\Co;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;

/**
 * @requires PHP 7.0
 */
class PrivateStaticTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;

    public function _before()
    {
        $this->Co = Proxy::get(Co::class);
    }

    public function testValidateOptions()
    {
        $validateOptionsEx = function ($v) {
            try {
                $this->Co::validateOptions($v);
                $this->assertTrue(false);
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        };

        $this->specify(
            "Interval validation",
        function () use ($validateOptionsEx) {
            $this->assertEquals(1.4, $this->Co::validateOptions(['interval' => '1.4'])['interval']);
            $this->assertEquals(2, $this->Co::validateOptions(['interval' => '2'])['interval']);
            $this->assertEquals(0.0, $this->Co::validateOptions(['interval' => 0])['interval']);
            $this->assertEquals(0.2, $this->Co::validateOptions(['interval' => '2e-1'])['interval']);
            $validateOptionsEx(['interval' => -1.0]);
            $validateOptionsEx(['interval' => '2e2e']);
        });

        $this->specify(
            "Concurrency validation",
        function () use ($validateOptionsEx) {
            $this->assertEquals(1, $this->Co::validateOptions(['concurrency' => 1.0])['concurrency']);
            $this->assertEquals(2, $this->Co::validateOptions(['concurrency' => '2'])['concurrency']);
            $this->assertEquals(0, $this->Co::validateOptions(['concurrency' => 0])['concurrency']);
            $validateOptionsEx(['concurrency' => '1.0']);
            $validateOptionsEx(['concurrency' => -1]);
        });

        $this->specify(
            "Boolean validation",
        function () use ($validateOptionsEx) {
            $this->assertEquals(true, $this->Co::validateOptions(['throw' => true])['throw']);
            $this->assertEquals(false, $this->Co::validateOptions(['pipeline' => 'off'])['pipeline']);
            $this->assertEquals(true, $this->Co::validateOptions(['multiplex' => 'yes'])['multiplex']);
            $validateOptionsEx(['throw' => 'ok']);
        });
    }

    public function testNormalize()
    {
        $this->specify(
            "Function should be replace with return value recursively",
        function () {
            $normalized = $this->Co::normalize(
                function () { return function () { return 1; }; }
            );
            $this->assertEquals(1, $normalized);
        });

        $this->specify(
            "Generator function should be replace with Generator",
        function () {
            $normalized = $this->Co::normalize(function () { yield 1; });
            $this->assertTrue($normalized instanceof \Generator);
        });

        $this->specify(
            "Empty Traversable should be instantly replaced with empty array",
        function () {
            $normalized = $this->Co::normalize(new \ArrayIterator);
            $this->assertEquals([], $normalized);
        });
    }

    public function testIsGeneratorRunning()
    {
        $this->specify(
            "Finished Generators should be false",
        function () {
            $this->assertFalse($this->Co::isGeneratorRunning(
                !($g = (function () { yield 1; return 1; })())
                ?: $g->next()
                ?: $g
            ));
        });

        $this->specify(
            "Running Generators should be true",
        function () {
            $this->assertTrue($this->Co::isGeneratorRunning(
                (function () { yield 1; })()
            ));
        });

        $this->specify(
            "Psuedo Generator returns should be false",
        function () {
            $this->assertFalse($this->Co::isGeneratorRunning(
                (function () { yield Co::RETURN_WITH => 1; })()
            ));
        });
    }

    public function testGetGeneratorReturn()
    {
        $this->specify(
            "Finished Generators should return value",
        function () {
            $this->assertEquals(1, $this->Co::getGeneratorReturn(
                !($g = (function () { yield 1; return 1; })())
                ?: $g->next()
                ?: $g
            ));
        });

        $this->specify(
            "Running Generators should throw LogicException",
        function () {
            $this->Co::getGeneratorReturn(
                (function () { yield 1; })()
            );
        }, ['throws' => 'LogicException']);

        $this->specify(
            "Psuedo Generator returns should be valid",
        function () {
            $this->assertEquals(1, $this->Co::getGeneratorReturn(
                (function () { yield Co::RETURN_WITH => 1; })()
            ));
        });
    }

    public function testIsCurl()
    {
        $this->specify(
            "Check if value is a valid cURL handle",
        function () {
            $this->assertTrue($this->Co::isCurl(curl_init()));
            $this->assertFalse($this->Co::isCurl(curl_close(curl_init())));
            $this->assertFalse($this->Co::isCurl(1));
        });
    }

    public function testIsGenerator()
    {
        $this->specify(
            "Generators should be true",
        function () {
            $this->assertTrue($this->Co::isGenerator(
                (function () { yield 1; })()
            ));
        });

        $this->specify(
            "Generator functions should be false",
        function () {
            $this->assertFalse($this->Co::isGenerator(
                function () { yield 1; }
            ));
        });

        $this->specify(
            "Arrays and Iterators should be false",
        function () {
            $this->assertFalse($this->Co::isGenerator(new \ArrayIterator));
            $this->assertFalse($this->Co::isGenerator([]));
        });
    }

    public function testArrayLike()
    {
        $this->specify(
            "Arrays and Iterators should be judged as ArrayLike",
        function () {
            $this->assertTrue($this->Co::isArrayLike([]));
            $this->assertTrue($this->Co::isArrayLike(new \ArrayIterator));
        });

        $this->specify(
            "Generator should be judged as NOT ArrayLike",
        function () {
            $this->assertFalse($this->Co::isArrayLike(
                (function () { yield 1; })()
            ));
        });
    }

    public function testFlatten()
    {
        $this->specify("Nested array should be flattened",
        function () {
            $from = [[['a', ['b'], [[['c', 'foo' => 'd']]]]]];
            $to = ['a', 'b', 'c', 'd'];
            $this->assertEquals($to, $this->Co::flatten($from));
        });

        $this->specify(
            "Arrays and Iterators should be flattened as Arrays, " .
            "but Generators should be ignored",
        function () {
            $gen = (function () { yield 1; })();
            $from = [
                new \RecursiveArrayIterator(['foo' => 1, [$gen], 2]),
                new \ArrayIterator([3, ['bar' => $gen], 4]),
            ];
            $to = [1, $gen, 2, 3, $gen, 4];
            $this->assertEquals($to, $this->Co::flatten($from));
        });
    }

}
