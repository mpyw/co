<?php

use mpyw\Co\Co;

/**
 * @requires PHP 5.5
 */
class PrivateStaticTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;

    private static function getMethod($method_name)
    {
        $rm = new \ReflectionMethod('mpyw\Co\Co', $method_name);
        return $rm->getClosure();
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
            "Check if value is a Generator",
        function () use ($isGenerator) {
            $gen = call_user_func(function () { yield 1; });
            $it = new ArrayIterator([1]);
            $this->assertTrue($isGenerator($gen));
            $this->assertFalse($isGenerator($it));
        });
    }

    public function testArrayLike()
    {
        $isArrayLike = self::getMethod('isArrayLike');

        $this->specify(
            "Arrays and Iterators should be judged as ArrayLike, " .
            "but Generators should be excluded",
        function () use ($isArrayLike) {
            $gen = call_user_func(function () { yield 1; });
            $arr = [1];
            $it = new \ArrayIterator([1]);
            $this->assertFalse($isArrayLike($gen));
            $this->assertTrue($isArrayLike($arr));
            $this->assertTrue($isArrayLike($it));
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
            $gen = call_user_func(function () { yield 1; });
            $from = [
                new \RecursiveArrayIterator([1, [$gen], 2]),
                new \ArrayIterator([3, [$gen], 4]),
            ];
            $to = [1, $gen, 2, 3, $gen, 4];
            $this->assertEquals($to, $flatten($from));
        });
    }

}
