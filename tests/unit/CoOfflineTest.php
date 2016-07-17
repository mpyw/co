<?php

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\CURLPool;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;

function vd(...$args)
{
    ob_start();
    var_dump(...$args);
    $data = ob_get_clean();
    file_put_contents('php://stderr', "\n$data\n");
}

/**
 * @requires PHP 7.0
 */
class CoOfflineTest extends \Codeception\TestCase\Test {
    use \Codeception\Specify;
    private static $pool;

    public function _before()
    {
        self::$pool = test::double([new CURLPool(new CoOption)]);
    }

    public function _after()
    {
    }

    public function testSimple()
    {
        $this->assertEquals([1], Co::wait([1]));

        $genfunc = function () {
            $x = yield 3;
            $y = yield 2;
            return $x + $y;
        };
        $this->assertEquals(5, Co::wait($genfunc));

        $genfunc = function () {
            $x = yield function () {
                yield Co::RETURN_WITH => yield function () {
                    return yield function () {
                        return 3;
                        yield null;
                    };
                    yield null;
                };
                yield null;
            };
            $y = yield 2;
            return $x + $y;
        };
        $this->assertEquals(5, Co::wait($genfunc));

        $genfunc = function () {
            return array_sum(array_map('current', yield [
                [function () { return 7; yield null; }],
                [function () { return 9; }],
                [function () { yield null; return 13; }],
            ]));
        };
        $this->assertEquals(29, Co::wait($genfunc));
    }
}
