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

    public function testValidateInterval()
    {
        $validateIntervalEx = function ($v) {
            try {
                $this->Co::validateInterval($v);
                $this->assertTrue(false);
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        };

        $this->specify(
            "Numeric should be float",
        function () {
            $this->assertEquals(0.0, $this->Co::validateInterval(0));
            $this->assertEquals(1.1, $this->Co::validateInterval('1.1'));
            $this->assertEquals(3e1, $this->Co::validateInterval('3e1'));
        });

        $this->specify(
            "Non-numeric should throw InvalidArgumentException",
        function () use ($validateIntervalEx) {
            $validateIntervalEx('foo');
            $validateIntervalEx([]);
            $validateIntervalEx((object)[]);
            $validateIntervalEx(null);
        });

        $this->specify(
            "Negative float should throw InvalidArgumentException",
        function () use ($validateIntervalEx) {
            $validateIntervalEx(-1.0);
        });
    }

    public function testValidateConcurrency()
    {
        $validateConcurrencyEx = function ($v) {
            try {
                $this->Co::validateConcurrency($v);
                $this->assertTrue(false);
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        };

        $this->specify(
            "Numeric should be integer",
        function () {
            $this->assertEquals(1, $this->Co::validateConcurrency(1));
            $this->assertEquals(2, $this->Co::validateConcurrency('2'));
            $this->assertEquals(3, $this->Co::validateConcurrency(3.1));
        });

        $this->specify(
            "Non-numeric should throw InvalidArgumentException",
        function () use ($validateConcurrencyEx) {
            $validateConcurrencyEx('foo');
            $validateConcurrencyEx([]);
            $validateConcurrencyEx((object)[]);
            $validateConcurrencyEx(null);
        });

        $this->specify(
            "Negative integer should throw InvalidArgumentException",
        function () use ($validateConcurrencyEx) {
            $validateConcurrencyEx(-1);
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
