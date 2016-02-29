<?php

use mpyw\Co\Co;

/**
 * @requires PHP 7.0
 */
class PrivateStaticTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;

    private static function getMethod($method_name)
    {
        $rm = new \ReflectionMethod('mpyw\Co\Co', $method_name);
        return $rm->getClosure();
    }

    public function testValidateInterval()
    {
        $validateInterval = self::getMethod('validateInterval');
        $validateIntervalEx = function ($v) use ($validateInterval) {
            try {
                $validateInterval($v);
                $this->assertTrue(false);
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        };

        $this->specify(
            "Numeric should be float",
        function () use ($validateInterval) {
            $this->assertEquals(0.0, $validateInterval(0));
            $this->assertEquals(1.1, $validateInterval('1.1'));
            $this->assertEquals(3e1, $validateInterval('3e1'));
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
        $validateConcurrency = self::getMethod('validateConcurrency');
        $validateConcurrencyEx = function ($v) use ($validateConcurrency) {
            try {
                $validateConcurrency($v);
                $this->assertTrue(false);
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        };

        $this->specify(
            "Numeric should be integer",
        function () use ($validateConcurrency) {
            $this->assertEquals(1, $validateConcurrency(1));
            $this->assertEquals(2, $validateConcurrency('2'));
            $this->assertEquals(3, $validateConcurrency(3.1));
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
        $normalize = self::getMethod('normalize');

        $this->specify(
            "Function should be replace with return value recursively",
        function () use ($normalize) {
            $normalized = $normalize(function () { return function () { return 1; }; });
            $this->assertEquals(1, $normalized);
        });

        $this->specify(
            "Generator function should be replace with Generator",
        function () use ($normalize) {
            $normalized = $normalize(function () { yield 1; });
            $this->assertTrue($normalized instanceof \Generator);
        });

        $this->specify(
            "Empty Traversable should be instantly replaced with empty array",
        function () use ($normalize) {
            $normalized = $normalize(new \ArrayIterator);
            $this->assertEquals([], $normalized);
        });
    }

    public function testIsGeneratorRunning()
    {
        $isGeneratorRunning = self::getMethod('isGeneratorRunning');

        $this->specify(
            "Finished Generators should be false",
        function () use ($isGeneratorRunning) {
            $this->assertFalse($isGeneratorRunning(
                !($g = (function () { yield 1; return 1; })())
                ?: $g->next()
                ?: $g
            ));
        });

        $this->specify(
            "Running Generators should be true",
        function () use ($isGeneratorRunning) {
            $this->assertTrue($isGeneratorRunning((function () { yield 1; })()));
        });

        $this->specify(
            "Psuedo Generator returns should be false",
        function () use ($isGeneratorRunning) {
            $this->assertFalse($isGeneratorRunning((function () { yield Co::RETURN_WITH => 1; })()));
        });
    }

    public function testGetGeneratorReturn()
    {
        $getGeneratorReturn = self::getMethod('getGeneratorReturn');

        $this->specify(
            "Finished Generators should return value",
        function () use ($getGeneratorReturn) {
            $this->assertEquals(1, $getGeneratorReturn(
                !($g = (function () { yield 1; return 1; })())
                ?: $g->next()
                ?: $g
            ));
        });

        $this->specify(
            "Running Generators should throw LogicException",
        function () use ($getGeneratorReturn) {
            $getGeneratorReturn((function () { yield 1; })());
            $this->assertTrue(false);
        }, ['throws' => 'LogicException']);

        $this->specify(
            "Psuedo Generator returns should be valid",
        function () use ($getGeneratorReturn) {
            $this->assertEquals(1, $getGeneratorReturn(
                (function () { yield Co::RETURN_WITH => 1; })()
            ));
        });
    }

    public function testIsCurl()
    {
        $isCurl = self::getMethod('isCurl');

        $this->specify(
            "Check if value is a valid cURL handle",
        function () use ($isCurl) {
            $this->assertTrue($isCurl(curl_init()));
            $this->assertFalse($isCurl(curl_close(curl_init())));
            $this->assertFalse($isCurl(1));
        });
    }

    public function testIsGenerator()
    {
        $isGenerator = self::getMethod('isGenerator');

        $this->specify(
            "Generators should be true",
        function () use ($isGenerator) {
            $this->assertTrue($isGenerator((function () { yield 1; })()));
        });

        $this->specify(
            "Generator functions should be false",
        function () use ($isGenerator) {
            $this->assertFalse($isGenerator(function () { yield 1; }));
        });

        $this->specify(
            "Arrays and Iterators should be false",
        function () use ($isGenerator) {
            $this->assertFalse($isGenerator(new \ArrayIterator));
            $this->assertFalse($isGenerator([]));
        });
    }

    public function testArrayLike()
    {
        $isArrayLike = self::getMethod('isArrayLike');

        $this->specify(
            "Arrays and Iterators should be judged as ArrayLike",
        function () use ($isArrayLike) {
            $this->assertTrue($isArrayLike([]));
            $this->assertTrue($isArrayLike(new \ArrayIterator));
        });

        $this->specify(
            "Generator should be judged as NOT ArrayLike",
        function () use ($isArrayLike) {
            $this->assertFalse($isArrayLike((function () { yield 1; })()));
        });
    }

    public function testFlatten()
    {
        $flatten = self::getMethod('flatten');

        $this->specify("Nested array should be flattened",
        function () use ($flatten) {
            $from = [[['a', ['b'], [[['c', 'foo' => 'd']]]]]];
            $to = ['a', 'b', 'c', 'd'];
            $this->assertEquals($to, $flatten($from));
        });

        $this->specify(
            "Arrays and Iterators should be flattened as Arrays, " .
            "but Generators should be ignored",
        function () use ($flatten) {
            $gen = (function () { yield 1; })();
            $from = [
                new \RecursiveArrayIterator(['foo' => 1, [$gen], 2]),
                new \ArrayIterator([3, ['bar' => $gen], 4]),
            ];
            $to = [1, $gen, 2, 3, $gen, 4];
            $this->assertEquals($to, $flatten($from));
        });
    }

}
