<?php

require_once __DIR__ . '/DummyCurl.php';
require_once __DIR__ . '/DummyCurlMulti.php';
require_once __DIR__ . '/DummyCurlFunctions.php';

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\CURLPool;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;

/**
 * @requires PHP 7.0
 */
class CoTest extends \Codeception\TestCase\Test {
    use \Codeception\Specify;
    private static $pool;

    public function _before()
    {
        test::double('mpyw\Co\Internal\Utils', ['isCurl' => function ($arg) {
            return $arg instanceof \DummyCurl;
        }]);
    }

    public function _after()
    {
        test::clean();
    }

    public function testWaitGenerator()
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

    public function testAsyncGenerator()
    {
        $i = 0;
        $genfunc = function () use (&$i) {
            Co::async(function () use (&$i) {
                ++$i;
            });
            Co::async(function () use (&$i) {
                ++$i;
            });
        };
        Co::wait($genfunc);
        $this->assertEquals($i, 2);
    }

    public function testWaitCurl()
    {
        $result = Co::wait([
            'A' => new DummyCurl('A', 2),
            [
                'B' => new DummyCurl('B', 3),
            ],
            [
                [
                    'C' => new DummyCurl('C', 2),
                ]
            ]
        ]);
        $this->assertEquals([
            'A' => 'Response[A]',
            [
                'B' => 'Response[B]',
            ],
            [
                [
                    'C' => 'Response[C]',
                ],
            ],
        ], $result);
    }
}
