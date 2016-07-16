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
                yield CoInterface::RETURN_WITH => function () {
                    return function () {
                        yield CoInterface::RETURN_WITH => 3;
                    };
                    yield null;
                };
            };
            vd($x);
            $y = yield 2;
            return $x + $y;
        };
        $this->assertEquals(5, Co::wait($genfunc));
    }
}
